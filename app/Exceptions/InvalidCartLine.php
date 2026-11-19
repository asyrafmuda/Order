<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a cart line cannot be sold as requested (item sold out, an
 * option that does not belong to the product, etc).
 */
class InvalidCartLine extends RuntimeException {}
