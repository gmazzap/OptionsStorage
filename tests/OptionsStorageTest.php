<?php
namespace GM\Tests;

use GM\OptionsStorage as O;

class OptionsStorageTest extends TestCase {

    function testSetGetDirectories() {
        $dirs = [ 'foo', 'bar', 'baz' ];
        $o = new O;
        $o->setDirectories( $dirs );
        assertEquals( [ 'foo', 'bar', 'baz' ], $o->getDirectories() );
    }

    function testSetGetDirectoriesFiltered() {
        $dirs = [ 'foo', 'bar', 'baz' ];
        $o = new O;
        $o->setDirectories( $dirs );
        \WP_Mock::onFilter( 'options_storage_dirs' )
            ->with( [ 'foo', 'bar', 'baz' ] )
            ->reply( [ 'foo', 'bar', 'baz', 'hello' ] );
        assertEquals( [ 'foo', 'bar', 'baz', 'hello' ], $o->getDirectories() );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    function testAddDirFailsIfBadDir() {
        $o = new O;
        $oo = $o->addDir( 'directory/foo' );
    }

    function testAddDir() {
        $o = new O;
        $oo = $o->addDir( __DIR__ );
        assertEquals( $o, $oo );
        assertEquals( [ __DIR__ ], $oo->getDirectories() );
    }

    function testAddDirFiltered() {
        \WP_Mock::onFilter( 'options_storage_dirs' )
            ->with( __DIR__ )
            ->reply( [ 'foo', 'bar' ] );
        $o = new O;
        $oo = $o->addDir( __DIR__ );
        assertEquals( $o, $oo );
        assertEquals( [ 'foo', 'bar' ], $oo->getDirectories() );
    }

    function testGetShortcut() {
        \WP_Mock::onFilter( 'options_storage_shortcut_foo' )
            ->with( NULL )
            ->reply( 'Code is Code' );
        $o = new O;
        assertEquals( 'Code is Code', $o->get( 'foo' ) );
    }

    function testGetFromDB() {
        $data = [
            'foo' => [
                'bar' => 'Bar',
                'baz' => 'Baz',
                'foofoo' => [
                    'barbar' => TRUE
                ]
            ]
        ];
        \WP_Mock::wpFunction( 'get_option', [
            'times' => 1,
            'args' => [ 'foo' ],
            'return' => $data
        ] );
        $o = new O;
        $o->fromDB( 'foo' );
        assertEquals( $data, $o->getDB(), 'Testing db' );
        assertEquals( $data[ 'foo' ], $o->get( 'foo' ), 'Testing 1st' );
        assertEquals( 'Bar', $o->get( 'foo.bar' ), 'Testing 2nd' );
        assertTrue( $o->get( 'foo.foofoo.barbar' ), 'Testing 3rd' );
        assertNull( $o->get( 'foo.bar.baz' ), 'Testing non-valid' );
        assertEquals( 'foo', $o->get( 'foo.bar.baz', 'foo' ), 'Testing non-valid and default' );
    }

    function testGetFromFile() {
        $o = new O;
        \WP_Mock::wpPassthruFunction( 'trailingslashit' );
        $o->addDir( __DIR__ . '/files/' );
        assertEquals( 'Eheheh!', $o->get( 'sample.three_level.d.e' ), 'Testing 3rd' );
        assertEquals( 'A', $o->get( 'sample.two_level.a' ), 'Testing 2nd' );
        assertEquals( [ 'a' => 'A', 'b' => 'B' ], $o->get( 'sample.two_level' ), 'Testing 1st' );
        assertEquals( 'Bar', $o->get( 'sample.bar' ), 'Testing 1st string' );
    }

    function testGet() {
        $data = [
            'sample' => [
                'baz' => 'BazDB',
                'two_level' => [
                    'b' => 'BDB'
                ],
                'three_level' => [
                    'c' => 'DB',
                    'd' => [
                        'd' => 'DB!'
                    ]
                ]
            ]
        ];
        \WP_Mock::wpFunction( 'get_option', [
            'times' => 1,
            'args' => [ 'sample' ],
            'return' => $data
        ] );
        \WP_Mock::wpPassthruFunction( 'trailingslashit' );
        $o = new O;
        $o->fromDB( 'sample' );
        $o->addDir( __DIR__ . '/files/' );
        assertEquals( 'Eheheh!', $o->get( 'sample.three_level.d.e' ), '3rd - file' );
        assertEquals( 'BDB', $o->get( 'sample.two_level.b' ), '2nd - db' );
        assertEquals( 'Bar', $o->get( 'sample.bar' ), '1st - file' );
        assertEquals( 'BazDB', $o->get( 'sample.baz' ), '1st - db' );
        assertEquals( [ 'a' => 'A', 'b' => 'BDB' ], $o->get( 'sample.two_level' ), 'Testing 1st' );
    }

    function testGetFiltered() {
        \WP_Mock::onFilter( 'options_storage_get' )->with( 'A', 'sample.two_level.a' )->reply( '!' );
        $o = new O;
        \WP_Mock::wpPassthruFunction( 'trailingslashit' );
        $o->addDir( __DIR__ . '/files/' );
        assertEquals( '!', $o->get( 'sample.two_level.a' ) );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    function testSetFailsIfBadId() {
        $o = new O;
        $o->set( '' );
    }

    function testSet() {
        $data = [
            'bar' => "Bar",
            'baz' => "Baz",
            'foofoo' => [
                'barbar' => "BarBar"
            ]
        ];
        $o = new O;
        $o->set( 'foo', $data );
        assertEquals( $data, $o->get( 'foo' ), '1st' );
        assertEquals( $data[ 'bar' ], $o->get( 'foo.bar' ), '2nd' );
        assertEquals( $data[ 'foofoo' ][ 'barbar' ], $o->get( 'foo.foofoo.barbar' ), '3rd' );
        assertNull( $o->get( 'foo.bar.baz' ), 'Testing non-valid' );
        assertEquals( 'foo', $o->get( 'foo.bar.baz', 'foo' ), 'non-valid & default' );
    }

    function testSetDeep() {
        $data = [
            'bar' => "Bar",
            'baz' => "Baz",
            'foofoo' => [
                'barbar' => "BarBar"
            ]
        ];
        $o = new O;
        $o->set( 'foo', $data );
        $o->set( 'foo.foofoo.barbar', 'New!' );
        assertEquals( 'New!', $o->get( 'foo.foofoo.barbar' ) );
    }

    /**
     * @expectedException \BadMethodCallException
     */
    function testSetDeepBad() {
        $data = [
            'bar' => "Bar",
            'baz' => "Baz",
            'foofoo' => [
                'barbar' => "BarBar"
            ]
        ];
        $o = new O;
        $o->set( 'foo', $data );
        $o->set( 'foo.foofoo.barbar.bad', 'Bad!' );
    }
}