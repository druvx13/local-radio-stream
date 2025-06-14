<?php
namespace App\Exceptions;

class AppException extends \Exception {
    protected $details;

    public function __construct($message = "", $code = 0, \Throwable $previous = null, $details = null) {
        parent::__construct($message, $code, $previous);
        $this->details = $details;
    }

    public function getDetails() {
        return $this->details;
    }
}
?>
