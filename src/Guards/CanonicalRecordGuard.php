<?php

declare(strict_types=1);

namespace WarrenBoswell\LaravelAiGuardrails\Guards;

use Illuminate\Contracts\Support\Arrayable;
use WarrenBoswell\LaravelAiGuardrails\Results\CanonicalGroundResult;

class CanonicalRecordGuard
{
    /**
     * Map AI-selected record IDs back onto canonical PHP/Eloquent records so metrics cannot be hallucinated.
     *
     * @param  iterable<array-key, array<string, mixed>|Arrayable<string, mixed>>  $canonicalRecords
     * @param  array<int, mixed>  $aiMatches
     * @param  list<string>  $allowedAiFields
     * @param  array<string, mixed>  $aiFieldDefaults
     */
    public function ground(
        iterable $canonicalRecords,
        array $aiMatches,
        string $canonicalKey = 'id',
        string $matchKey = 'id',
        array $allowedAiFields = ['match_reason'],
        array $aiFieldDefaults = [],
        bool $renameCanonicalIdToMatchKey = false,
    ): CanonicalGroundResult {
        $recordsById = [];

        foreach ($canonicalRecords as $record) {
            if ($record instanceof Arrayable) {
                $record = $record->toArray();
            }

            if (! is_array($record)) {
                continue;
            }

            $id = (string) ($record[$canonicalKey] ?? '');

            if ($id !== '') {
                $recordsById[$id] = $record;
            }
        }

        $grounded = [];
        $seenIds = [];
        $strippedIds = [];
        $duplicateIds = [];
        $fieldsToMerge = array_values(array_unique(array_merge($allowedAiFields, array_keys($aiFieldDefaults))));

        foreach ($aiMatches as $match) {
            if (! is_array($match)) {
                continue;
            }

            $recordId = (string) ($match[$matchKey] ?? $match[$canonicalKey] ?? '');

            if ($recordId === '') {
                $strippedIds[] = '(missing)';

                continue;
            }

            if (isset($seenIds[$recordId])) {
                $duplicateIds[] = $recordId;

                continue;
            }

            if (! isset($recordsById[$recordId])) {
                $strippedIds[] = $recordId;

                continue;
            }

            $seenIds[$recordId] = true;
            $canonical = $recordsById[$recordId];
            $row = [];

            if ($renameCanonicalIdToMatchKey && $matchKey !== $canonicalKey) {
                $row[$matchKey] = $canonical[$canonicalKey];

                foreach ($canonical as $key => $value) {
                    if ($key !== $canonicalKey) {
                        $row[$key] = $value;
                    }
                }
            } else {
                $row = $canonical;
            }

            foreach ($fieldsToMerge as $field) {
                // Canonical database attributes can never be overwritten by AI output.
                if (array_key_exists($field, $canonical)) {
                    continue;
                }

                if (array_key_exists($field, $match) && $match[$field] !== null) {
                    $row[$field] = $match[$field];
                } elseif (array_key_exists($field, $aiFieldDefaults)) {
                    $row[$field] = $aiFieldDefaults[$field];
                }
            }

            $grounded[] = $row;
        }

        $verifiedCount = count($grounded);
        $strippedCount = count($strippedIds);
        $duplicateCount = count($duplicateIds);

        $headline = $strippedCount > 0 || $duplicateCount > 0
           ? sprintf('PHP Canonical Guard Stripped %d Invalid/Duplicate Match(es)', $strippedCount + $duplicateCount)
           : 'PHP Canonical Grounding Passed';

        $detail = sprintf(
            '%d canonical record(s) hydrated; %d hallucinated ID(s) and %d duplicate(s) removed.',
            $verifiedCount,
            $strippedCount,
            $duplicateCount,
        );

        return new CanonicalGroundResult(
            items: $grounded,
            verifiedCount: $verifiedCount,
            strippedCount: $strippedCount,
            strippedIds: $strippedIds,
            duplicateIds: $duplicateIds,
            headline: $headline,
            detail: $detail,
        );
    }
}
