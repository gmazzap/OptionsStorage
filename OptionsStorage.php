<?php namespace Brain;

class OptionsStorage implements \ArrayAccess {

    protected $storage = [ ];

    protected $db = [ ];

    protected $files = [ ];

    protected $directories = [ ];

    protected $option;

    public function __construct( $option = NULL ) {
        if ( ! is_null( $option ) ) {
            $this->option = $option;
            $this->fromDB( $option );
        }
    }

    public function getDirectories( $id = NULL ) {
        return apply_filters( 'options_storage_dirs', $this->directories, $id );
    }

    public function setDirectories( Array $dirs ) {
        $this->directories = $dirs;
        return $this;
    }

    public function addDir( $dir ) {
        if ( ! is_string( $dir ) || ! is_dir( $dir ) ) throw new \InvalidArgumentException;
        $this->directories[] = $dir;
        return $this;
    }

    public function get( $id, $default = NULL ) {
        $sc = apply_filters( "options_storage_shortcut_{$id}", NULL );
        if ( ! is_null( $sc ) ) {
            return $sc;
        }
        $val = $this->resolve( $id );
        if ( is_null( $val ) ) {
            $i = explode( '.', $id );
            $this->load( "{$i[0]}.php" );
            $val = $this->resolve( $id, $default );
        }
        return apply_filters( 'options_storage_get', $val, $id );
    }

    public function set( $id, $value = NULL ) {
        if ( ! is_string( $id ) || empty( $id ) ) throw new \InvalidArgumentException;
        $count = substr_count( $id, '.' );
        if ( $count === 0 ) {
            $this->storage[$id] = $value;
        } else {
            $this->setDeep( $id, $value, $count );
        }
    }

    public function load( $id ) {
        if ( ! is_string( $id ) ) throw new \InvalidArgumentException;
        $files = $this->getFilesLoaded();
        if ( isset( $files[$id] ) ) return;
        if ( is_file( $id ) ) return $this->loadFile( $id );
        $dirs = $this->getDirectories( $id );
        foreach ( $dirs as $dir ) {
            $path = trailingslashit( $dir ) . $id;
            if ( is_file( $path ) ) {
                $this->files[] = $id;
                return $this->loadFile( $path );
            }
        }
    }

    public function toDB( $option = NULL ) {
        if ( ! is_null( $option ) && ! is_string( $option ) ) throw new \InvalidArgumentException;
        if ( is_null( $option ) && ! empty( $this->option ) ) $option = $this->option;
        if ( empty( $option ) ) {
            throw new \BadMethodCallException( __METHOD__ . ' needs an option name.' );
        }
        $save = $this->storage;
        $dont_save = apply_filter( 'options_storage_skip_todb', NULL );
        if ( ! empty( $dont_save ) && is_array( $dont_save ) ) {
            foreach ( $dont_save as $key ) {
                if ( is_string( $key ) && isset( $save[$dont_save] ) ) {
                    unset( $save[$dont_save] );
                }
            }
        }
        update_option( $option, $save );
        return $this;
    }

    public function fromDB( $option ) {
        if ( ! is_string( $option ) ) throw new \InvalidArgumentException;
        $db = get_option( $option );
        if ( is_array( $db ) && ! empty( $db ) ) {
            $this->db = $db;
            $this->storage = $db;
        }
    }

    public function getDB() {
        return $this->db;
    }

    public function getFilesLoaded() {
        return $this->files;
    }

    public function flush() {
        $this->storage = [ ];
        $this->db = [ ];
        $this->files = [ ];
    }

    public function flushDB() {
        $this->db = [ ];
    }

    public function flushFiles( $which = NULL ) {
        if ( is_array( $which ) ) {
            $this->files = array_diff( $this->files, $which );
            return;
        } elseif ( is_string( $which ) && isset( $this->files[$which] ) ) {
            unset( $this->files[$which] );
            return;
        }
        $this->files = [ ];
    }

    protected function resolve( $id, $default = NULL ) {
        $link = $this->storage;
        foreach ( explode( '.', $id ) as $key ) {
            if ( ! isset( $link[$key] ) ) {
                return $default;
            }
            $link = $link[$key];
        }
        return $link;
    }

    protected function setDeep( $id, $value, $count ) {
        $link = &$this->storage;
        $i = 0;
        foreach ( explode( '.', $id ) as $i => $key ) {
            if ( ! isset( $link[$key] ) ) {
                $link[$key] = [ ];
            } elseif ( ! is_array( $link[$key] ) && ( $count !== $i ) ) {
                $msg = "Bad " . __METHOD__ . " call using {$id} {$key} leaf is not an array.";
                throw new \BadMethodCallException( $msg );
            } elseif ( ! is_array( $link[$key] ) && ( $count === $i ) ) {
                $link = array_merge( $link, [ "{$key}" => $value ] );
                return;
            }
            $link = &$link[$key];
        }
        $link = $value;
    }

    protected function loadFile( $path ) {
        $options = include $path;
        if ( ! is_array( $options ) ) throw new \DomainException;
        $name = pathinfo( $path, PATHINFO_FILENAME );
        $db = $this->getDB();
        if ( isset( $db[$name] ) && is_array( $db[$name] ) ) {
            $options = array_replace_recursive( $options, $db[$name] );
        }
        $this->storage[$name] = $options;
        return $options;
    }

    public function offsetExists( $offset ) {
        return ! is_null( $this->get( $offset ) );
    }

    public function offsetGet( $offset ) {
        return $this->get( $offset );
    }

    public function offsetSet( $offset, $value ) {
        return $this->set( $offset, $value );
    }

    public function offsetUnset( $offset ) {
        return $this->set( $offset, NULL );
    }

}