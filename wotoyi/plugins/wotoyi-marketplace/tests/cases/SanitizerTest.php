<?php

use Wotoyi\Marketplace\Schema\Fields;
use Wotoyi\Marketplace\Schema\VehicleFields;

final class SanitizerTest extends Wotoyi_Test_Case {

	private function clean( array $payload ): array {
		return Fields::sanitize( VehicleFields::defs(), $payload, VehicleFields::ENVELOPE );
	}

	public function test_everything_is_optional(): void {
		$r = $this->clean( array() );
		$this->assertSame( array(), $r['warnings'] );
		$this->assertNull( $r['clean']['make'] );
		$this->assertNull( $r['clean']['price'] );
	}

	public function test_invalid_enum_is_dropped_with_warning(): void {
		$r = $this->clean( array( 'fuel_type' => 'steam', 'body_type' => 'SUV' ) );
		$this->assertNull( $r['clean']['fuel_type'] );
		$this->assertSame( 'suv', $r['clean']['body_type'], 'enum is case-insensitive' );
		$this->assertContains( 'invalid_value:fuel_type', $r['warnings'] );
	}

	public function test_integers_are_range_checked_and_strict(): void {
		$r = $this->clean( array( 'year' => '2021', 'mileage_km' => -5, 'seats' => 5.5, 'doors' => 'five', 'price' => 1e30 ) );
		$this->assertSame( 2021, $r['clean']['year'] );
		$this->assertNull( $r['clean']['mileage_km'] );
		$this->assertNull( $r['clean']['seats'] );
		$this->assertNull( $r['clean']['doors'] );
		$this->assertNull( $r['clean']['price'] );
		$this->assertContains( 'invalid_value:price', $r['warnings'] );
	}

	public function test_decimals_and_geo_bounds(): void {
		$r = $this->clean( array( 'latitude' => '14.716677', 'longitude' => 200, 'consumption_combined' => '6.46' ) );
		$this->assertSame( 14.716677, $r['clean']['latitude'] );
		$this->assertNull( $r['clean']['longitude'] );
		$this->assertSame( 6.5, $r['clean']['consumption_combined'] );
	}

	public function test_booleans_and_dates(): void {
		$r = $this->clean( array( 'accident_free' => 'true', 'warranty' => 'maybe', 'first_registration_date' => '2021-02-30', 'last_service_date' => '2024-05-01' ) );
		$this->assertTrue( $r['clean']['accident_free'] );
		$this->assertNull( $r['clean']['warranty'] );
		$this->assertNull( $r['clean']['first_registration_date'], 'impossible date rejected' );
		$this->assertSame( '2024-05-01', $r['clean']['last_service_date'] );
	}

	public function test_urls_only_http_https(): void {
		$r = $this->clean( array( 'cover_image' => 'javascript:alert(1)', 'video_url' => 'https://cdn.example.com/v.mp4', 'gallery' => array( 'https://a.test/1.jpg', 'data:text/html;base64,AAA', 'ftp://x/y.jpg' ) ) );
		$this->assertNull( $r['clean']['cover_image'] );
		$this->assertSame( 'https://cdn.example.com/v.mp4', $r['clean']['video_url'] );
		$this->assertSame( array( 'https://a.test/1.jpg' ), $r['clean']['gallery'] );
	}

	public function test_html_is_stripped_from_text_and_description(): void {
		$r = $this->clean( array( 'title' => '<script>alert(1)</script>Corolla', 'description' => '<p onclick="x()">Nice <script>bad()</script><strong>car</strong></p><img src=x onerror=y>' ) );
		$this->assertStringNotContains( '<', (string) $r['clean']['title'] );
		$this->assertStringNotContains( '<script', (string) $r['clean']['description'] );
		$this->assertStringNotContains( 'onclick', (string) $r['clean']['description'] );
		$this->assertStringNotContains( '<img', (string) $r['clean']['description'] );
		$this->assertStringContains( '<strong>car</strong>', (string) $r['clean']['description'] );
	}

	public function test_managed_trust_fields_cannot_be_set_by_dealers(): void {
		$r = $this->clean( array( 'wotoyi_inspected' => true, 'inspection_report_url' => 'https://x.test/r.pdf' ) );
		$this->assertArrayNotHasKey( 'wotoyi_inspected', $r['clean'] );
		$this->assertContains( 'managed_field_ignored:wotoyi_inspected', $r['warnings'] );
		$this->assertContains( 'managed_field_ignored:inspection_report_url', $r['warnings'] );
	}

	public function test_unknown_fields_are_reported_not_stored(): void {
		$r = $this->clean( array( 'make' => 'Kia', 'evil_column' => 'x', 'dealer_id' => 9, 'source_id' => 'a', 'version' => 1 ) );
		$this->assertArrayNotHasKey( 'evil_column', $r['clean'] );
		$this->assertContains( 'unknown_field:evil_column', $r['warnings'] );
		$this->assertNotContains( 'unknown_field:dealer_id', $r['warnings'], 'envelope keys are not "unknown"' );
	}

	public function test_equipment_codes_are_whitelisted_per_category(): void {
		$r = $this->clean( array( 'equipment' => array( 'comfort' => array( 'air_conditioning', 'laser_cannon', 'cruise_control' ), 'safety' => array( 'abs', 'air_conditioning' ), 'bogus' => array( 'abs' ) ) ) );
		$this->assertSame( array( 'comfort' => array( 'air_conditioning', 'cruise_control' ), 'safety' => array( 'abs' ) ), $r['clean']['equipment'] );
	}

	public function test_i18n_description_accepts_only_supported_languages(): void {
		$r = $this->clean( array( 'description_i18n' => array( 'fr' => 'Bonjour', 'wo' => 'Nanga def', 'de' => 'Hallo', 'ar' => 'مرحبا' ) ) );
		$this->assertSame( array( 'fr', 'wo', 'ar' ), array_keys( $r['clean']['description_i18n'] ) );
	}

	public function test_country_and_currency_codes(): void {
		$r = $this->clean( array( 'origin_country' => 'fr', 'country' => 'SEN', 'currency' => 'xof' ) );
		$this->assertSame( 'FR', $r['clean']['origin_country'] );
		$this->assertNull( $r['clean']['country'] );
		$this->assertSame( 'XOF', $r['clean']['currency'] );
	}

	public function test_gallery_is_capped(): void {
		$urls = array();
		for ( $i = 0; $i < 100; $i++ ) {
			$urls[] = "https://cdn.example.com/{$i}.jpg";
		}
		$r = $this->clean( array( 'gallery' => $urls ) );
		$this->assertCount( 40, $r['clean']['gallery'] );
	}

	public function test_non_scalar_garbage_is_rejected_safely(): void {
		$r = $this->clean( array( 'make' => array( 'x' ), 'year' => array( 1 ), 'price' => new stdClass(), 'gallery' => 'notalist' ) );
		$this->assertNull( $r['clean']['make'] );
		$this->assertNull( $r['clean']['year'] );
		$this->assertNull( $r['clean']['price'] );
		$this->assertNull( $r['clean']['gallery'] );
	}
}
