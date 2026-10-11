<?php

namespace App\Filament\Filters;

use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Date: [field] From [..] To [..]" table filter.
 *
 * $fields maps a key to [label, apply], where apply is
 * fn (Builder $query, ?string $from, ?string $until) and from/until are
 * Y-m-d strings (either may be empty). The first field is the default.
 * Both ends are inclusive, and the CSV export follows it because exports
 * use the filtered table query.
 */
class DateRangeFilter
{
    /**
     * @param  array<string, array{0: string, 1: callable}> $fields
     */
    public static function make(string $name, array $fields): Filter
    {
        $options = array_map(fn ($f) => $f[0], $fields);
        $default = array_key_first($fields);

        return Filter::make($name)
            ->form([
                Grid::make(count($fields) > 1 ? 3 : 2)->schema(array_values(array_filter([
                    count($fields) > 1
                        ? Select::make('field')->label('Date')->options($options)->default($default)->selectablePlaceholder(false)
                        : null,
                    DatePicker::make('from')->label('From')->native(false)->displayFormat('d M Y')->closeOnDateSelection()
                        ->maxDate(fn ($get) => $get('until') ?: null),
                    DatePicker::make('until')->label('To')->native(false)->displayFormat('d M Y')->closeOnDateSelection()
                        ->minDate(fn ($get) => $get('from') ?: null),
                ]))),
            ])
            ->columnSpan(2)
            ->query(function (Builder $query, array $data) use ($fields, $default) {
                $from = $data['from'] ?? null;
                $until = $data['until'] ?? null;
                if (blank($from) && blank($until)) {
                    return $query;
                }
                $field = $fields[$data['field'] ?? $default] ?? $fields[$default];
                return ($field[1])($query, $from ? Carbon::parse($from)->toDateString() : null, $until ? Carbon::parse($until)->toDateString() : null) ?? $query;
            })
            ->indicateUsing(function (array $data) use ($options, $default): array {
                $from = $data['from'] ?? null;
                $until = $data['until'] ?? null;
                if (blank($from) && blank($until)) {
                    return [];
                }
                $label = $options[$data['field'] ?? $default] ?? $options[$default];
                $text = match (true) {
                    filled($from) && filled($until) => Carbon::parse($from)->format('d M Y') . ' – ' . Carbon::parse($until)->format('d M Y'),
                    filled($from) => 'from ' . Carbon::parse($from)->format('d M Y'),
                    default => 'until ' . Carbon::parse($until)->format('d M Y'),
                };
                return [Indicator::make("{$label}: {$text}")];
            });
    }

    /** Standard apply callback for a date/datetime column on the table itself. */
    public static function column(string $column): callable
    {
        return fn (Builder $q, ?string $from, ?string $until) => $q
            ->when($from, fn ($q) => $q->whereDate($column, '>=', $from))
            ->when($until, fn ($q) => $q->whereDate($column, '<=', $until));
    }

    /** Apply callback for a date column on a relation. */
    public static function relation(string $relation, string $column): callable
    {
        return fn (Builder $q, ?string $from, ?string $until) => $q->whereHas($relation, fn ($r) => $r
            ->when($from, fn ($r) => $r->whereDate($column, '>=', $from))
            ->when($until, fn ($r) => $r->whereDate($column, '<=', $until)));
    }
}
