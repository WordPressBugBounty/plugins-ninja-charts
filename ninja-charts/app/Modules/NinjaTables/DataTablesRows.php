<?php

namespace NinjaCharts\App\Modules\NinjaTables;

use NinjaCharts\App\Helpers\Helper;
use NinjaCharts\Framework\Support\Arr;

/**
 * Rows of a Ninja Tables table that uses the DataTables engine.
 *
 * Those tables keep their rows in a table of their own (wp_ninja_tables_dt_{id}) instead of
 * wp_ninja_table_items, so they are read through Ninja Tables' RowReader, which knows both
 * kinds of storage. The chart's row settings (how many rows, picked rows, a date range and
 * the order) are applied here, as the queries for the shared items table do for other tables.
 *
 * Rows are streamed and filtered as they are read, so a chart of the first few rows of a
 * large table holds only those rows in memory.
 *
 * Differences from the shared items table, by design:
 * - With no row count (or 0) every row is charted. The items table falls back to 15 there,
 *   a page size of the query builder rather than a choice; the editor always sends a count.
 * - Rows from a date range follow the chart's order. The items table returns them unordered.
 * - The ninja_charts_ntm_selected_table_rows filter is not applied, as it receives item models.
 */
class DataTablesRows
{
    const ENGINE_CLASS = '\NinjaTables\App\Services\Table\Engine';
    const READER_CLASS = '\NinjaTables\App\Services\Table\RowReader';

    /**
     * Whether the table stores its rows in a DataTables table. Ninja Tables before 5.5
     * has no such engine, so this is false there.
     */
    public static function applies($tableId)
    {
        if (!class_exists(self::ENGINE_CLASS) || !class_exists(self::READER_CLASS)) {
            return false;
        }

        $engine = self::ENGINE_CLASS;

        return (bool) $engine::isDataTables((int) $tableId);
    }

    /**
     * The row values the chart is drawn from, after its row settings.
     *
     * @param int        $tableId
     * @param array|null $rowSettings the chart's `row` settings
     * @param array|null $dateBounds  ['Y-m-d 00:00:00', 'Y-m-d 23:59:59'], or null when the range is unusable
     * @return array[] one array of column key => value per row
     */
    public static function values($tableId, $rowSettings, $dateBounds)
    {
        $rows = self::select(self::stream($tableId), $rowSettings, $dateBounds);

        return array_map(function ($row) {
            return is_array($row->value) ? $row->value : [];
        }, $rows);
    }

    /**
     * Every row, for picking rows by hand. Shaped like wp_ninja_table_items rows: the id,
     * and the values as JSON.
     *
     * @return array[]
     */
    public static function pickable($tableId, $rowSettings)
    {
        $rows = self::sorted(iterator_to_array(self::stream($tableId), false), $rowSettings);

        return array_map(function ($row) {
            return [
                'id'    => $row->id,
                'value' => wp_json_encode(is_array($row->value) ? $row->value : []),
            ];
        }, $rows);
    }

    /**
     * The first rows as they were added, shaped like pickable(), to show what each column holds.
     * Stops reading once it has them.
     *
     * @return array[]
     */
    public static function sample($tableId, $limit)
    {
        $rows = [];
        foreach (self::stream($tableId) as $row) {
            $rows[] = [
                'id'    => $row->id,
                'value' => wp_json_encode(is_array($row->value) ? $row->value : []),
            ];
            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * Applies the chart's row settings to rows as RowReader gives them
     * (objects with id, position, value, created_at and more). Takes any iterable, so a
     * generator is filtered as it is read.
     *
     * @param iterable   $rows
     * @param array|null $rowSettings
     * @param array|null $dateBounds
     * @return object[] in the chart's order
     */
    public static function select($rows, $rowSettings, $dateBounds)
    {
        $rowSettings = is_array($rowSettings) ? $rowSettings : [];
        $pickRange   = Arr::get($rowSettings, 'pick_range');
        $pickDate    = Arr::get($rowSettings, 'pick_date');

        // The first rows only
        if ($pickRange === 'false' && $pickDate === 'false') {
            $number = (int) Arr::get($rowSettings, 'number', 0);

            return $number > 0
                ? self::firstRows($rows, $number, $rowSettings)
                : self::sorted(self::collect($rows), $rowSettings);
        }

        // Rows picked by hand
        if ($pickRange === 'true' && $pickDate === 'false') {
            $picked = self::pickedIds(Arr::get($rowSettings, 'selected_row', []));
            if (!$picked) {
                return self::sorted(self::collect($rows), $rowSettings);
            }

            return self::sorted(self::collect($rows, function ($row) use ($picked) {
                return isset($picked[(int) $row->id]);
            }), $rowSettings);
        }

        // Rows added within a date range
        if ($pickRange === 'false' && $pickDate === 'true' && Arr::get($rowSettings, 'date_range') !== null) {
            if (!$dateBounds) {
                return [];
            }
            list($from, $to) = $dateBounds;

            return self::sorted(self::collect($rows, function ($row) use ($from, $to) {
                $created = (string) $row->created_at;

                return $created >= $from && $created <= $to;
            }), $rowSettings);
        }

        return self::sorted(self::collect($rows), $rowSettings);
    }

    /**
     * @return \Generator<object>|array
     */
    protected static function stream($tableId)
    {
        $reader = self::READER_CLASS;

        return (new $reader((int) $tableId))->each();
    }

    /**
     * Ids of the rows picked by hand, as keys for a quick lookup. Entries without an id are skipped.
     */
    protected static function pickedIds($selected)
    {
        $ids = [];
        foreach ((array) $selected as $item) {
            if (is_array($item) && isset($item['id'])) {
                $ids[(int) $item['id']] = true;
            }
        }

        return $ids;
    }

    protected static function collect($rows, $keep = null)
    {
        $kept = [];
        foreach ($rows as $row) {
            if (!$keep || $keep($row)) {
                $kept[] = $row;
            }
        }

        return $kept;
    }

    /**
     * The first rows in the chart's order, holding at most twice that many while reading:
     * the buffer is sorted and cut back whenever it fills.
     */
    protected static function firstRows($rows, $number, $rowSettings)
    {
        $compare = self::comparator($rowSettings);
        $buffer  = [];

        foreach ($rows as $row) {
            $buffer[] = $row;
            if (count($buffer) >= $number * 2) {
                usort($buffer, $compare);
                $buffer = array_slice($buffer, 0, $number);
            }
        }

        usort($buffer, $compare);

        return array_slice($buffer, 0, $number);
    }

    protected static function sorted($rows, $rowSettings)
    {
        usort($rows, self::comparator($rowSettings));

        return $rows;
    }

    /**
     * By the table's manual order, then by when each row was added, in the chart's direction.
     * The id breaks ties, so rows that share a position keep a steady order.
     */
    protected static function comparator($rowSettings)
    {
        $order     = Arr::get(is_array($rowSettings) ? $rowSettings : [], 'order', 'ASC');
        $direction = Helper::getOrderBy(is_string($order) ? $order : 'ASC') === 'DESC' ? -1 : 1;

        return function ($a, $b) use ($direction) {
            $byPosition = (int) $a->position <=> (int) $b->position;
            if ($byPosition !== 0) {
                return $byPosition * $direction;
            }
            $byCreated = strcmp((string) $a->created_at, (string) $b->created_at);
            if ($byCreated !== 0) {
                return $byCreated * $direction;
            }

            return ((int) $a->id <=> (int) $b->id) * $direction;
        };
    }
}
