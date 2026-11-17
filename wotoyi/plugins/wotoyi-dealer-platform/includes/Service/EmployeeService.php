<?php
/**
 * Employees (HR records; a login is a Member, an employee may or may not have one).
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Service;

use Wotoyi\DealerPlatform\Schema\Entities;

defined( 'ABSPATH' ) || exit;

final class EmployeeService extends EntityService {

	public function __construct() {
		parent::__construct( 'employees', Entities::get( 'employees' ) );
	}

	protected function defaults(): array {
		return array( 'status' => 'active' );
	}
}
