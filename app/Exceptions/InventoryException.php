<?php

namespace App\Exceptions;

use DomainException;

/**
 * Raised when an inventory operation violates a business rule.
 */
class InventoryException extends DomainException {}
