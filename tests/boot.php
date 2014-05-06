<?php
$path = dirname( dirname( __FILE__ ) );
if ( is_file( $path . '/vendor/autoload.php' ) ) {
    require_once $path . '/vendor/autoload.php';
}
if ( is_file( $path . '/vendor/phpunit/phpunit/PHPUnit/Framework/Assert/Functions.php' ) ) {
    require_once $path . '/vendor/phpunit/phpunit/PHPUnit/Framework/Assert/Functions.php';
}
unset( $path );
