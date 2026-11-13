<?php
/**
 * Built-in natural-language search (no external service).
 *
 * Turns a sentence such as "SUV diesel automatique à Dakar moins de 20 millions"
 * into catalog filters. It is the assistant's engine when no AI provider is
 * configured, and its safety net when the provider is unavailable.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Smart_Search {

	/**
	 * Extract catalog filters from free text (French, English, a few Wolof words).
	 *
	 * @param string $text User sentence.
	 * @return array Raw filters, to be passed through Vehicles::sanitize_filters().
	 */
	public static function parse( $text ) {
		$t       = ' ' . remove_accents( mb_strtolower( wp_strip_all_tags( (string) $text ) ) ) . ' ';
		$filters = array();
		$facets  = Vehicles::facets();

		// Sale or rental.
		if ( preg_match( '/\b(louer|location|loue|rent|rental|hire|luye|luwe)\b/', $t ) ) {
			$filters['type'] = 'rent';
		} elseif ( preg_match( '/\b(acheter|achat|vendre|vente|buy|purchase|sale|jend|jënd)\b/u', $t ) ) {
			$filters['type'] = 'sale';
		}

		// Brand and model, matched against what is really in the catalog.
		foreach ( $facets['brands'] as $brand_slug => $brand ) {
			$brand_name = remove_accents( mb_strtolower( $brand['name'] ) );
			$has_brand  = false !== strpos( $t, ' ' . $brand_name ) || false !== strpos( $t, str_replace( '-', ' ', $brand_slug ) );
			foreach ( $brand['models'] as $model_slug => $model ) {
				$model_name = remove_accents( mb_strtolower( $model ) );
				if ( mb_strlen( $model_name ) >= 3 && preg_match( '/\b' . preg_quote( $model_name, '/' ) . '\b/', $t ) ) {
					$filters['brand'] = $brand_slug;
					$filters['model'] = $model_slug;
					break 2;
				}
			}
			if ( $has_brand ) {
				$filters['brand'] = $brand_slug;
				break;
			}
		}

		foreach ( $facets['cities'] as $city_slug => $city ) {
			if ( preg_match( '/\b' . preg_quote( remove_accents( mb_strtolower( $city ) ), '/' ) . '\b/', $t ) ) {
				$filters['city'] = $city_slug;
				break;
			}
		}

		$keywords = array(
			'fuel'         => array(
				'diesel'   => 'diesel|gasoil|gazoil',
				'petrol'   => 'essence|petrol|gasoline',
				'hybrid'   => 'hybride|hybrid',
				'electric' => 'electrique|electric',
			),
			'transmission' => array(
				'automatic' => 'automatique|automatic|boite auto',
				'manual'    => 'manuelle|manuel|manual',
			),
			'body'         => array(
				'suv'       => 'suv|4x4|4 x 4|tout terrain|crossover',
				'sedan'     => 'berline|sedan|saloon',
				'pickup'    => 'pick up|pickup|pick-up',
				'hatchback' => 'citadine|hatchback|petite voiture|small car',
				'van'       => 'minibus|utilitaire|van|fourgon',
			),
			'condition'    => array(
				'new'      => 'neuve|neuf|brand new|zero km',
				'imported' => 'venant|importee|importe|imported',
			),
		);
		foreach ( $keywords as $filter => $options ) {
			foreach ( $options as $value => $pattern ) {
				if ( preg_match( '/\b(' . $pattern . ')\b/', $t ) ) {
					$filters[ $filter ] = $value;
					break;
				}
			}
		}

		// Year: "2020", "après 2019", "since 2018".
		if ( preg_match( '/\b(apres|depuis|a partir de|from|since|after)\s+((?:19|20)\d{2})\b/', $t, $m ) ) {
			$filters['year_min'] = (int) $m[2];
		} elseif ( preg_match( '/\b((?:19|20)\d{2})\b/', $t, $m ) ) {
			$filters['year_min'] = (int) $m[1];
			$filters['year_max'] = (int) $m[1];
		}

		// Amounts: "15 millions", "15m", "8 500 000", "30 000", "25k".
		$amounts = array();
		if ( preg_match_all( '/(\d+(?:[.,]\d+)?)\s*(millions?|m\b)/', $t, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			foreach ( $m as $match ) {
				$amounts[ $match[0][1] ] = (int) round( (float) str_replace( ',', '.', $match[1][0] ) * 1000000 );
			}
		}
		if ( preg_match_all( '/(\d+)\s*k\b/', $t, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			foreach ( $m as $match ) {
				$amounts[ $match[0][1] ] = (int) $match[1][0] * 1000;
			}
		}
		if ( preg_match_all( '/\b(\d{1,3}(?:[ .]\d{3})+|\d{5,9})\b/', $t, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			foreach ( $m as $match ) {
				$amounts[ $match[0][1] ] = (int) preg_replace( '/\D/', '', $match[1][0] );
			}
		}
		ksort( $amounts );
		$amounts = array_values( $amounts );
		if ( count( $amounts ) >= 2 && preg_match( '/\b(entre|between)\b/', $t ) ) {
			$filters['price_min'] = min( $amounts[0], $amounts[1] );
			$filters['price_max'] = max( $amounts[0], $amounts[1] );
		} elseif ( $amounts ) {
			if ( preg_match( '/\b(plus de|au moins|minimum|more than|above|over)\b/', $t ) ) {
				$filters['price_min'] = $amounts[0];
			} else {
				$filters['price_max'] = $amounts[0]; // A budget is a maximum by default.
			}
		}

		// Mileage: "moins de 80 000 km".
		if ( preg_match( '/(\d{1,3}(?:[ .]?\d{3})+)\s*km/', $t, $m ) ) {
			$filters['mileage_max'] = (int) preg_replace( '/\D/', '', $m[1] );
			$km                     = $filters['mileage_max'];
			foreach ( array( 'price_max', 'price_min' ) as $key ) {
				if ( isset( $filters[ $key ] ) && $filters[ $key ] === $km ) {
					unset( $filters[ $key ] );
				}
			}
		}

		if ( preg_match( '/\b(moins cher|pas cher|cheapest|cheap|economique|budget)\b/', $t ) ) {
			$filters['sort'] = 'price_asc';
		}

		return $filters;
	}

	/**
	 * Answer a visitor message without any AI provider.
	 *
	 * @param string $text Visitor message.
	 * @return array { text: string, vehicles: array, url: string }
	 */
	public static function answer( $text ) {
		$filters = Vehicles::sanitize_filters( self::parse( $text ) );

		if ( ! $filters ) {
			return array(
				'text'     => __( 'Tell me what you are looking for — for example: "Automatic SUV in Dakar under 20 million" or "car to rent this weekend". You can mention a brand, a budget, a city, the fuel type or the gearbox.', 'automotive-marketplace-core' ),
				'vehicles' => array(),
				'url'      => '',
			);
		}

		$results = Vehicles::search( $filters, 1, 6 );
		$url     = amc_catalog_url( 'all', amc_filters_to_query( $filters ) );

		if ( ! $results['total'] ) {
			return array(
				'text'     => __( 'I could not find a vehicle matching all these criteria right now. Try a higher budget, another city or fewer criteria.', 'automotive-marketplace-core' ),
				'vehicles' => array(),
				'url'      => amc_catalog_url(),
			);
		}

		return array(
			/* translators: %d: number of vehicles */
			'text'     => sprintf( _n( 'I found %d vehicle matching your search:', 'I found %d vehicles matching your search. Here are the first ones:', $results['total'], 'automotive-marketplace-core' ), $results['total'] ),
			'vehicles' => array_map( array( 'AMC\\Api', 'vehicle_summary' ), $results['items'] ),
			'url'      => $url,
		);
	}
}
