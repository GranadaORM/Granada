<?php

use Granada\Model;
use Granada\ResultSet;

/**
 * Models for use during test of eager loading
 *
 * @author Peter Schumacher <peter@schumacher.dk>
 */
class Manufactor extends Model
{
    public function cars()
    {
        return $this->has_many('Car')->where('enabled', 1);
    }
}

class Owner extends Model
{
    public function car()
    {
        return $this->has_one('Car');
    }
}

class Part extends Model
{
    public function cars()
    {
        return $this->has_many_through('Car');
    }
}

/**
 * @property string $someProperty
 */
class Car extends Model
{
    public function manufactor()
    {
        return $this->belongs_to('Manufactor')->where('enabled', 1);
    }

    public function owner()
    {
        return $this->belongs_to('Owner');
    }

    public function parts()
    {
        return $this->has_many_through('Part');
    }

    public function get_existingProperty($value)
    {
        return strtolower($value);
    }

    public function get_nonExistentProperty()
    {
        return 'test';
    }

    public function missing_someProperty()
    {
        return 'This property is missing';
    }

    public $missingonceCallCount = 0;

    public function missingonce_expensiveProperty()
    {
        $this->missingonceCallCount++;

        return 'expensive value';
    }

    public function missingonce_anotherProperty()
    {
        return 'another value';
    }

    public function set_name($value)
    {
        return 'test';
    }

    public static function filter_byName($query, $name)
    {
        return $query->where('name', $name);
    }

    protected static function _defaultFilter($query): \Granada\Orm\Wrapper
    {
        return $query->where('car.is_deleted', 0);
    }
}

class CarPart extends Model {}

/**
 * Models for use during testing
 */
class Simple extends Model {}
class ComplexModelClassName extends Model {}

/**
 * Its numeric column has a database default, for testing that an
 * explicitly set zero still reaches the insert.
 */
class Sale extends Model {}

/**
 * Its name column is unique, for testing transaction rollback on a
 * mid-loop insert failure.
 */
class Tag extends Model {}

/**
 * Its table allows duplicate, null and zero ids, for testing how
 * find_many keys its results.
 */
class KeyedRow extends Model
{
    public static $_table = 'keyed_row';
}
class KeyedRowCustomId extends Model
{
    public static $_table     = 'keyed_row_custom';
    public static $_id_column = 'custom_id';
}
class ModelWithCustomTable extends Model
{
    public static $_table = 'custom_table';
}
class ModelWithCustomTableAndCustomIdColumn extends Model
{
    public static $_table     = 'custom_table';
    public static $_id_column = 'custom_id_column';
}
class ModelWithFilters extends Model
{
    public static function name_is_fred($orm)
    {
        return $orm->where('name', 'Fred');
    }

    public static function name_is($orm, $name)
    {
        return $orm->where('name', $name);
    }
}
class ModelWithCustomConnection extends Model
{
    public const ALTERNATE          = 'alternate';
    public static $_connection_name = self::ALTERNATE;
}

class Profile extends Model
{
    public function user()
    {
        return $this->belongs_to('User');
    }
}
class User extends Model
{
    public function profile()
    {
        return $this->has_one('Profile');
    }
}
class UserTwo extends Model
{
    public function profile()
    {
        return $this->has_one('Profile', 'my_custom_fk_column');
    }
}
class ProfileTwo extends Model
{
    public function user()
    {
        return $this->belongs_to('User', 'custom_user_fk_column');
    }
}
class Post extends Model {}
class UserThree extends Model
{
    public function posts()
    {
        return $this->has_many('Post');
    }
}
class UserFour extends Model
{
    public function posts()
    {
        return $this->has_many('Post', 'my_custom_fk_column');
    }
}

class Author extends Model {}
class AuthorBook extends Model {}
class Book extends Model
{
    public function authors()
    {
        return $this->has_many_through('Author');
    }
}
class BookTwo extends Model
{
    public function authors()
    {
        return $this->has_many_through('Author', 'AuthorBook', 'custom_book_id', 'custom_author_id');
    }
}

class BookThree extends Model
{
    public function authors()
    {
        return $this->has_many_through('Author', null, null, null, 'id', 'id');
    }
}

/**
 * Its default filter deliberately adds two where conditions.
 */
class Gadget extends Model
{
    protected static function _defaultFilter($query): \Granada\Orm\Wrapper
    {
        return $query->where('enabled', 1)->where('hidden', 0);
    }

    public function widgets()
    {
        return $this->has_many('Widget');
    }

    public function named_widgets()
    {
        return $this->has_many('Widget')->where('name', 'Widget1');
    }

    public function featured()
    {
        return $this->has_one('Widget');
    }
}

class Widget extends Model
{
    protected static function _defaultFilter($query): \Granada\Orm\Wrapper
    {
        return $query->where('enabled', 1)->where('hidden', 0);
    }

    public function gadget()
    {
        return $this->belongs_to('Gadget');
    }

    public function kits()
    {
        return $this->has_many_through('Kit');
    }
}

class CustomResultSet extends ResultSet {}

class WidgetWithCustomResultSet extends Model
{
    public static $_table                = 'widget';
    public static string $resultSetClass = CustomResultSet::class;
}

class GadgetWithCustomResultSet extends Model
{
    public static $_table = 'gadget';

    public function custom_widgets()
    {
        return $this->has_many('WidgetWithCustomResultSet');
    }
}

/**
 * Its default filter deliberately adds two where conditions.
 */
class Kit extends Model
{
    protected static function _defaultFilter($query): \Granada\Orm\Wrapper
    {
        return $query->where('enabled', 1)->where('hidden', 0);
    }
}

class KitWidget extends Model {}

class MockPrefix_Simple extends Model {}
class MockPrefix_TableSpecified extends Model
{
    public static $_table = 'simple';
}

class QueryHintModel extends Model
{
    public static function _query_standin_class(): string
    {
        return QueryHintQuery::class;
    }
}

class QueryHintQuery extends Model {}

class OrDefaultFilterGadget extends Model
{
    public static $_table = 'gadget';

    protected static function _defaultFilter($query)
    {
        return $query->where('enabled', 1)->or_where('hidden', 1);
    }
}
