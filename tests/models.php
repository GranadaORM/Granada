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

    public function set_price($value)
    {
        if ($value instanceof Money) {
            return $value->amount;
        }

        return $value;
    }
}

/**
 * The money object Part's set_price() turns into a database amount.
 */
class Money
{
    public function __construct(
        public readonly float $amount,
    ) {}
}

/**
 * @property string $someProperty
 *
 * Its manufacture_date column converts a DateTime through
 * set_manufacture_date(), for testing update_many().
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

    public function set_manufacture_date($value)
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $value;
    }
}

class QueryCar extends \Granada\Query
{
    public function defaultFilter(): static
    {
        return $this->where('car.is_deleted', 0);
    }

    public function byName(string $name): static
    {
        return $this->where('name', $name);
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
    protected static ?string $_soft_delete_column = 'hidden';

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

    /**
     * A relation with its own select(), so a related-count subquery
     * must ignore that select list.
     */
    public function picked_widgets()
    {
        return $this->has_many('Widget')->select('widget.id');
    }
}

class QueryGadget extends \Granada\Query
{
    public function defaultFilter(): static
    {
        return $this->where('enabled', 1);
    }
}

class Widget extends Model
{
    public function gadget()
    {
        return $this->belongs_to('Gadget');
    }

    public function kits()
    {
        return $this->has_many_through('Kit');
    }
}

class QueryWidget extends \Granada\Query
{
    public function defaultFilter(): static
    {
        return $this->where('enabled', 1)->where('hidden', 0);
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
class Kit extends Model {}

class QueryKit extends \Granada\Query
{
    public function defaultFilter(): static
    {
        return $this->where('enabled', 1)->where('hidden', 0);
    }
}

class KitWidget extends Model {}

class MockPrefix_Simple extends Model {}
class MockPrefix_TableSpecified extends Model
{
    public static $_table = 'simple';
}

/**
 * The short name q() resolves through the prefix, with a table of
 * its own.
 */
class TableSpecified extends Model
{
    public static $_table = 'simple';
}

class OrDefaultFilterGadget extends Model
{
    public static $_table = 'gadget';
}

class QueryOrDefaultFilterGadget extends \Granada\Query
{
    public function defaultFilter(): static
    {
        return $this->where('enabled', 1)->or_where('hidden', 1);
    }
}

/**
 * Its views column is a nullable counter, for testing increment()
 * and decrement().
 */
class Counter extends Model {}

/**
 * Resolution-order models: the per-model query class resolves as
 * Query<Model>, then BaseQuery<Model>, then Granada\Query.
 */
class ResolutionBoth extends Model {}
class QueryResolutionBoth extends \Granada\Query {}
class BaseQueryResolutionBoth extends \Granada\Query {}

class ResolutionBaseOnly extends Model {}
class BaseQueryResolutionBaseOnly extends \Granada\Query {}

class ResolutionNone extends Model {}

class Machine extends Model
{
    public function bolts()
    {
        return $this->has_many_through('Bolt', 'MachineBolt', 'machine_id', 'bolt_id');
    }

    public function direct_parts()
    {
        return $this->has_many('Part', 'machine_id');
    }

    public function ordered_bolts()
    {
        return $this->has_many_through('Bolt', 'MachineBolt', 'machine_id', 'bolt_id')
            ->ordered_by('sort_order');
    }

    /**
     * A link column that holds a string key, joined through another
     * table's column rather than its id.
     */
    public function bolt_codes()
    {
        return $this->has_many_through('Bolt', 'MachineBoltCode', 'machine_id', 'bolt_code', other_table_key: 'code');
    }
}

class Bolt extends Model
{
    public function machines()
    {
        return $this->has_many_through('Machine', 'MachineBolt', 'bolt_id', 'machine_id');
    }
}

class MachineBolt extends Model {}

class MachineBoltCode extends Model {}

/**
 * A parent with a plain has_many of items, an ordered has_many, and a
 * has_many of soft-delete extras.
 */
class Crate extends Model
{
    public function items()
    {
        return $this->has_many('CrateItem', 'crate_id');
    }

    public function ordered_items()
    {
        return $this->has_many('CrateItem', 'crate_id')->ordered_by('sort_order');
    }

    public function extras()
    {
        return $this->has_many('CrateExtra', 'crate_id');
    }

    /**
     * A relation with its own filter, so sync operates on the
     * filtered subset the read sees.
     */
    public function named_items()
    {
        return $this->has_many('CrateItem', 'crate_id')->where('name', 'Item1');
    }
}

class CrateItem extends Model {}

class CrateExtra extends Model
{
    protected static ?string $_soft_delete_column = 'is_deleted';
}

/**
 * Turns automatic timestamps on, with the default columns.
 */
class Stamped extends Model
{
    protected static bool $_auto_timestamps = true;
}

/**
 * A model that does not turn timestamps on.
 */
class Unstamped extends Model {}

/**
 * A base that turns timestamps on with custom column names and one
 * ignored field.
 */
abstract class StampedChildBase extends Model
{
    protected static bool $_auto_timestamps = true;

    protected static array $_auto_timestamp_columns = [
        'created' => 'made_on',
        'updated' => 'changed_on',
    ];

    protected static array $_auto_timestamps_ignore = ['sort_order'];
}

/**
 * Inherits the switch, the custom columns, and the ignore list.
 * Its last_login column pins the suffix path's timezone adjustment.
 */
class StampedChild extends StampedChildBase
{
    public static $_table = 'stamped_child';

    public function adjustTimezoneForWhere(string $varname, mixed $value): mixed
    {
        return $varname === 'last_login' ? $value . '@utc' : $value;
    }
}

/**
 * A subclass that replaces the ignore list the base declared.
 */
class StampedChildOverride extends StampedChildBase
{
    public static $_table = 'stamped_override';

    protected static array $_auto_timestamps_ignore = ['last_login'];
}

/**
 * A soft-delete model that turns timestamps on.
 */
class StampedSoft extends Model
{
    public static $_table = 'stamped_soft';

    protected static ?string $_soft_delete_column = 'is_deleted';

    protected static bool $_auto_timestamps = true;
}

/**
 * Its ignore list names the soft-delete flag, so marking a row writes
 * no timestamp.
 */
class StampedSoftIgnored extends Model
{
    public static $_table = 'stamped_soft_ignored';

    protected static ?string $_soft_delete_column = 'is_deleted';

    protected static bool $_auto_timestamps = true;

    protected static array $_auto_timestamps_ignore = ['is_deleted'];
}
