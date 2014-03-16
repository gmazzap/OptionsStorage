OptionsStorage
==============


OptionsStorage is a simple and light (one class, ~180 lines of code) options container for WordPress.

It can be used to store configuration in PHP files using PHP array and access in read/write using "dot notation".

It is **not** a full plugin, but it's intended to be used in larger projects and embedded via [Composer](https://getcomposer.org/).

It requires **PHP 5.4+**, so array short syntax can be used in configuration files.


##Table of Contents##

- [Add to your projects](#add-to-your-projects)
- [Usage](#usage)
  - [Configuration files](#configuration-files)
  - [Register folders](#register-folders)
    - [Folders hook](#folders-hook)
  - [Get data](#get-data)
    - [Defaults](#defaults)
    - [Get hooks](#get-hooks)
    - #[Array access](#array-access)
  - [Set Data](#set-data)
  -[Database freezing](#database-freezing)
    - [Preserving files data](#preserving-files-data)
  - [Restore from db](#restore-from-db)
  - [DB and files data naming conflicts](#db-and-files-data-naming-conflicts)
  - [Loading files "manually"](#loading-files-manually)
  - [Flushing](#flushing)
    - [Flushing files](#flushing-files)
    - [Flushing DB](#flushing-db)
    - [Flushing all](#flushing-all)


---

##Add to your projects##

In `composer.json` add `"zoomlab/options-storage": "dev-master"` to `require` object and `"https://github.com/Giuseppe-Mazzapica/OptionsStorage"` to `repositories` array.

Something like:

    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/Giuseppe-Mazzapica/OptionsStorage"
        }
    ],
    "require": {
        "php": ">=5.4",
        "zoomlab/options-storage": "dev-master"
    }
    
When installing via composer, note that OptionsStorage comes with PHP-Unit test suite, however, packages needed for tests are in `require-dev` object. See [Composer docs](https://getcomposer.org/doc/03-cli.md#install) for more info.

Also note that package unit tests works out of the box when OptionsStorage is installed standalone, (`vendor` folder inside package folder), otherwise additional configuration is needed. See [`tests/boot.php`](tests/boot.php) source.
    
##Usage##

Use OptionsStorage is pretty simple:
 1. Create some configuration files
 2. Register directories in the container
 3. Get and set data

###Configuration files###

A configuration file, is a standard PHP file that returns an associative array. 

    // /path/to/wp/wp-content/themes/my-theme/conf/conf-parent.php
    return [
      'foo' => 'Foo!',
      'bar' => 'Bar!',
      'greetings' => [
        'meet' => [
          'before12' => 'Good morning',
          'after12' => 'Good evening'
        ],
        'leave' => 'Goodbye'
      ]
    ];
    
    // /path/to/wp/wp-content/themes/my-child-theme/conf/conf-child.php
    return [
      'fruits' => [
        'red' => [
          'strawberries'
        ],
        'yellow' => [
          'banana',
          'lemon'
        ]
      ]
    ];
    
###Register folders###

To register folders in the container, we need a container instance. then is possible to call on it:
 - `setDirectories` passing an array of paths
 - `addDir` passing a single folder path.

A call `setDirectories will overwrite the directories previously setted.

    $storage = new GM\OptionsStorage;
    $storage->setDirectories( STYLESHEETPATH . '/conf', TEMPLATEPATH. '/conf' );
    $storage->addDir( plugin_dir_path( __FILE__ ) . 'conf' );
    
Please note that register directories does **not** mean that all php files in those directories are loaded,
but we says to OptionsStorage where to look for files if needed: files are loaded only if required and only once.

####Folders hook####

An alternative to register folders using the 2 methods explained above, is possible to use the filter hook `'options_storage_dirs'` to change the folders. This hook pass to hooking callbaks 2 params: the 1st is, off course, the actual registered folders, the second is the request id (see [Get hooks](#get-hooks) section below) 

    /**
     * Skip stylesheet path for the option 'conf-child.foo'
     */
     add_filter( 'options_storage_dirs', function( $dirs, $id ) {
       if ( $id === 'conf-child.foo' ) $dirs = [ TEMPLATEPATH. '/conf' ];
       return $dirs;
     });

Nothe that, as written, previous filter works only if added before any option inside the file `conf-child.php` is used. That because, as already said, file are loaded only once, and so if the file was already loaded OptionsStorage will not traverse directory to finde the file again, so the filter is ignored.
However is possible force one, some or all files to be loaded again using the [`flushFiles()`](#flushing-files).



###Get data###

After the 3 lines of code above we can access to data using the `get` method with "dot notation", i.e. access to
multidimensional array using array keys *glued* by dots. Code will be more clear, assuming conf files posted above:

    $greeting = $storage->get( 'conf-parent.greetings.meet.after12' );
    var_dump( $greeting ); // string(12) "Good evening" 
    $red_fruit = $storage->get( 'conf-child.fruits.red' );
    var_dump( $red_fruit ); // array(1) { [0]=> string(12) "strawberries" }
    $yellow_fruits = $storage->get( 'conf-child.fruits.yellow' );
    var_dump( $yellow_fruits ); // array(2) { [0]=> string(6) "banana" [1]=> string(5) "lemon" }
    
So, the first part of the dotted string is the name of the php file without extension. Note that there is no need to specify the folder because the class will look in the registered folders. If a file named `conf-parent.php` exists in more than one directory, than the first found will win (searching folders in order of addition).

Files are loaded only when if a configuration are getted, otherwise file will never be loaded. Of course, files are loaded only once, data are cached so all request after first are returned from cache.

####Defaults####

`get()` method accepts a second argument allow setting a default value if wanted data is not found:

    $greeting = $storage->get( 'conf-parent.greetings.friend-meet' ); // without default
    var_dump( $greeting ); // NULL
    $greeting = $storage->get( 'conf-parent.greetings.friend-meet', 'Hi there!' ); // with default
    var_dump( $greeting ); // string(9) "Hi there!"
    
####Get hooks####

`get()` method trigger 2 filter hooks, one *before* get data, other *after*

 - `"options_storage_shortcut_{$id}"` is triggered before, so returning a non-null value on that filter means does not search in configuration data. This hook is  variable, the `$id` part is whatever is passed as 1st param to `get()`. This filters pass to hooking callbacks only one arguments, the actual value (`NULL`, by default)
 - 'options_storage_get' is triggered after the data is retrieved. This hook pass to hooking callbacks 2 params: 1st is, of course, the found data (or default, when applicable) and 2nd param is the same of `$id` in previous hook

        $test = $storage->get( 'conf-parent.foo' );
        var_dump( $test ); // string(4) "Foo!"
    
        add_filter( 'options_storage_shortcut_conf-parent.foo', function() {
          return 'Bar!';
        });
        $test = $storage->get( 'conf-parent.foo' );
        var_dump( $test ); // string(4) "Bar!"
    
        $test = $storage->get( 'conf-parent.bar' );
        var_dump( $test ); // string(4) "Bar!"
    
        add_filter( 'options_storage_get', function( $actual, $id ) {
          if ( $id === 'conf-parent.bar') $actual = 'Foo!';
          return $actual;
        });
        $test = $storage->get( 'conf-parent.bar' );
        var_dump( $test ); // string(4) "Foo!"


####Array access####

As alternative to the `get` method, is also possible to get data using the array access interface:

    $test = $storage->get( 'conf-parent.foo' );
    var_dump( $test ); // string(4) "Foo!"
    
    $test = $storage[ 'conf-parent.foo' ]; // <-- use $storage object as an array 
    var_dump( $test ); // string(4) "Foo!"


###Set data###

OptionsStorage allow to set data on runtime using the same dot notation used to get data. 
Of course, files are **not** edited, what change is the data one can retrieve using `get()` method on the same request:

    $yellow_fruits = $storage->get( 'conf-child.fruits.yellow' );
    var_dump( $yellow_fruits ); // array(2) { [0]=> string(6) "banana" [1]=> string(5) "lemon" }
    
    $new_yellow = [ 'banana', 'lemon', 'pineapple' ];
    $storage->set( 'conf-child.fruits.yellow', $new_yellow );
    
    // array(3) { [0]=> string(6) "banana" [1]=> string(5) "lemon" [2]=> string(9) "pineapple" } 
    var_dump( $storage['conf-child.fruits.yellow'] );



Array access syntax can be uset to set data, too.

Set an load methods are useful, mostly when used in combination with database freezing.

###Database freezing###

One useful feature od OptionsStorage is the possibility to save the current state of the container in WordPress database.
That is as easy as call the method `toDB()` on the container instance. It accepts one argument that is the option name: in  effects, what this method does is just save a serialized option by calling the WP core [`update_option`](http://codex.wordpress.org/Function_Reference/update_option) function.

Please keep in mind: `toDB()` save the current state of container, i. e. all the options already loaded via files or setted via `set()` method. Options in files never loaded (so never used or *manually* loaded via `load()` before calling `toDB()`) are not saved in database.

    $storage->get('conf-child'); 
    $storage->get('conf-parent');
    // now the current storage state contains both files data, let's freeze to db
    $storage->toDB( 'my-option' );

####Preserving files data####
Sometimes can be desiderable that during a `toDB()` call some data are **not** saved in database, (so in next requests that data are always taken from files).

This task can be done in 2 ways:

 - use the filter `'options_storage_skip_todb'`. Using this method is possible only skip a whole file data, e. g. all the `conf-child.php`
 - use `set()` method and set to `NULL` the data to preserve

Both methods works, of course, if applyed before call `toDB()`.

    $storage->get('conf-child'); 
    $storage->get('conf-parent');
    // avoiding 'conf-child' data are saved in database
    add_filter( 'options_storage_skip_todb', function( $preserve = NULL ) {
      $preserve = (array) $preserve;
      return array_merge( $preserve, [ 'conf-child' ] );
    });
    $storage->toDB();
    
As shown above, callback hooking into `'options_storage_skip_todb'` filter, must return an array, where values are all the files names (with no extension, as usual) that want to be skipped.

###Restore from db###
This task can be done in 2 ways:
 - calling the `fromDB()` method and pass to it the option name
 - pass the option name to constructor

    $storage = new GM\OptionsStorage;
    $storage->fromDB( 'my-option' );
    
    // following line does the same thing of previous two
    $storage = new GM\OptionsStorage( 'my-option' );

When `fromDB()` is called, **entire** container state is overriden with anything in database (unless otion is empty, in that case method does nothing). So, using the constructor method is possible to avoid unwanted state override.
The constructor method has another effect: when used (even if the option is empty), then the `toDB()` can be called without any argument: the option name passed to constructor will be used as default.

###DB and files data naming conflicts###

As [said](#database-freezing) `toDB()` method save the current state of container, so if `set()` method is used to set to `NULL` some file-based data before call `toDB()` (as explained [here](#preserving-files-data)) is possible that when workin with db-restored container one want to access data from files even if there is naming confict, an example using file defined in [Configuration files](#configuration-files) section:

    $storage = new GM\OptionsStorage('my-option');
    $storage->setDirectories( STYLESHEETPATH . '/conf', TEMPLATEPATH. '/conf' );
    $yellow_fruits = $storage->get( 'conf-child.fruits.yellow' );
    var_dump( $yellow_fruits ); // array(2) { [0]=> string(6) "banana" [1]=> string(5) "lemon" }
    // removing yellow fruits
    $storage->set( 'conf-child.fruits.yellow', NULL );
    // add raspberries to red fruits
    $storage->set( 'conf-child.fruits.red', [ 'strawberries', 'raspberries' ] );
    // test
    $yellow_fruits = $storage->get( 'conf-child.fruits.yellow' );
    var_dump( $yellow_fruits ); // NULL
    // database freeze
    $storage->toDB();
    
On a subsequent request now we do:

    $storage = new GM\OptionsStorage('my-option'); // this time the state is what we freezed in previous code
    $storage->setDirectories( STYLESHEETPATH . '/conf', TEMPLATEPATH. '/conf' );
    
    $red_fruits = $storage->get( 'conf-child.fruits.red' );
    // result from database
    var_dump( $red_fruits ); // array(2) { [0]=> string(12) "strawberries" [1]=> string(11) "raspberries" }
    
    $yellow_fruits = $storage->get( 'conf-child.fruits.yellow' );
    // result from files (on database was set to null)
    var_dump( $yellow_fruits ); // array(2) { [0]=> string(6) "banana" [1]=> string(5) "lemon" }
    
How is possible that `$yellow_fruits` contains the file-based data and `$red_fruits` contain database data? Reason is simple: on every get call, if the current state contain db data, but the requested data index is not find, OptionsStorage look on file and load it if necessary, but this does not override database data, because in case of naming conflics php function [`array_replace_recursive`](http://www.php.net/manual/en/function.array-replace-recursive.php) (where db data replace file data) is applied on conflict data.
    

###Loading files "manually"###

Sometimes can be desiderable to load a file that is not in one of the registered folders. It can be done using the `load()` method.

    $storage->load( '/absolute/path/to/a/conf/file' );
    
Worth nothing 3 things:
 - if the file is named just like an already loaded file it will not be loaded, unless is used the [`flushFiles()`](#flushing-files) method, in that case new loaded data will overwrite the old one
 - if the current status is restored form database, then database data takes precedence over files data, even if file is loaded after the database restore, and the [general rule](#db-and-files-data-naming-conflicts) (`array_replace_recursive` method) is still valid for naminng conflicting data. To be sure current data contains *all** data just loaded from a file, the [`flushFiles()`](#flushing-files) method should be used in combination with [`flushDB()`](#flushing-db) method
 - `load()` method immediately load the file, opposed to `get()` that load files only if needed.

Last thing to know about `load()` is that is possible to call it passing only a file name (and not the absolute path): in that case the file is searched in the registered directories. Used like so, it is a sort of alias of `get()` when used with the *first piece* (file name without extension) of option id.

The difference is that `get` triggers [filters](#get-hooks), `load` doesn't

    $storage->get( 'conf-child' );
    // equivalent to previous, without filters
    $storage->load( 'conf-child.php' );
    
###Flushing###

OptionsStorage use some caching methods (file are loaded only once, etc) and also prevent that database restored data are overwritten by files data (see [here](#db-and-files-data-naming-conflicts)).

However sometimes, for any reason, one may want to overcome these constraints, this is the reason for flushing methods. There are 3 types of flushings in OptionsStorage:

- files flushing
- db flushing
- general flushing

####Flushing files###
Flushing files allow to force OptionsStorage to ignore file cache for one, some or all files. This meand that when data coming for a file is requested again (or [explicitily loaded](#loading-files-manually)) OptionsStorage will load that files again, instead of looking in its cache.

Files flushing is done via `flushFiles()` method:

 - when called with a file name (with no extension) as string, `$storage->flushFiles('a-file')`, it flushes that specific files
 - when called with an array of file names, `$storage->flushFiles( [ 'a-file', 'another' ] )`, it flushes the file specified files
 - when called with no args (or with any non-string and non-array value), `$storage->flushFiles()` it flushes all files

####Flushing DB###
Flushing database means that all the database data in containser are deleted. Is not posibble, (at least not in current release) selectively flushing database data.
Flush db is usefule, e.g. when we want to force the container to load data from files. Method to call is **`$storage->flushDB()`**

####Flushing all###
Last flushing method is the simplest: **`$storage->flush()`**: this method reset all the container state: a container flushed using this method is *blank*, equal to one just instanciated (with no arg to constructor).




    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
