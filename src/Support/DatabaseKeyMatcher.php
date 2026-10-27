<?php

namespace Lapaliv\BulkUpsert\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Builders\WhereClauseApplier;
use Lapaliv\BulkUpsert\Exceptions\BulkIdentifierIsAmbiguous;

/**
 * Matches the given models with the selected rows by the equality of the database.
 *
 * The rows are matched in PHP by their serialized unique values first. The database may find
 * a row equal to a value which PHP does not (a case-insensitive or an accent-insensitive
 * collation, a number in another form): such a row stays unmatched in PHP although it is the
 * stored version of the input. Here the database itself decides, by the same `column = value`
 * conditions which have found the row, restricted to the rows which are still unmatched.
 *
 * It runs one query per unmatched input, only when there are unmatched selected rows, so the
 * usual path keeps its number of queries. It is not a general check of ambiguous keys: it only
 * refuses to pick one of several unmatched rows equal to the same input.
 *
 * @internal
 */
readonly class DatabaseKeyMatcher
{
    /**
     * Create a new matcher instance.
     *
     * @param \Lapaliv\BulkUpsert\Builders\WhereClauseApplier $whereClauseApplier
     */
    public function __construct(
        private WhereClauseApplier $whereClauseApplier,
    )
    {
        //
    }

    /**
     * Match the inputs with the candidates; each candidate is given to one input at most.
     *
     * @param \Illuminate\Database\Eloquent\Model $eloquent
     * @param string[] $uniqueBy
     * @param array<int, \Illuminate\Database\Eloquent\Model> $inputs The unmatched inputs by any key.
     * @param list<\Illuminate\Database\Eloquent\Model> $candidates The selected rows which no input has matched.
     *
     * @return array<int, \Illuminate\Database\Eloquent\Model> The matched candidates by the keys of the inputs.
     *
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkIdentifierIsAmbiguous
     */
    public function match(Model $eloquent, array $uniqueBy, array $inputs, array $candidates): array
    {
        $keyName = $eloquent->getKeyName();
        /** @var array<string, \Illuminate\Database\Eloquent\Model> $byKey */
        $byKey = [];

        foreach ($candidates as $candidate) {
            /** @psalm-suppress MixedAssignment */
            $key = $candidate->getAttribute($keyName);

            if (is_int($key) || is_string($key)) {
                $byKey[(string) $key] = $candidate;
            }
        }

        $result = [];

        foreach ($inputs as $index => $input) {
            if ($byKey === []) {
                break;
            }

            // The candidates have passed the scopes and the soft-delete policy of the lookup
            // already, so the query is limited to their keys without the scopes. Two rows are
            // enough to tell that the input is ambiguous.
            /** @var list<int|string> $found */
            $found = $eloquent->newQueryWithoutScopes()
                ->useWritePdo()
                ->whereKey(array_keys($byKey))
                ->where(function (Builder $builder) use ($uniqueBy, $input): void {
                    $this->whereClauseApplier->handle($builder, $uniqueBy, [$input]);
                })
                ->limit(2)
                ->pluck($keyName)
                ->all();

            if (count($found) > 1) {
                throw new BulkIdentifierIsAmbiguous($input, $uniqueBy);
            }

            if (count($found) === 1 && isset($byKey[(string) $found[0]])) {
                $result[$index] = $byKey[(string) $found[0]];
                unset($byKey[(string) $found[0]]);
            }
        }

        return $result;
    }
}
