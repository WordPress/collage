<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Decorate an already validated PDF AST with exact source occurrence edges.
 *
 * This phase is isolated from the final occurrence audit so each compact
 * class can be compiled at a low-memory boundary.
 */
final class PdfSourceOutputDecorator
{
    private const SEMANTIC_STRUCTURE_MAPPING_MODE = 'exact-semantic-list-marker';

    /**
     * @param list<AstNode> $blocks
     * @param array{text:string,leaves:list<array<string,mixed>>,blocks:list<array<string,mixed>>} $output
     * @param list<array<string,mixed>> $ranges
     * @return array{blocks:list<AstNode>,mappingBySourceId:array<string,array<string,mixed>>}|null
     */
    public static function decorate(array $blocks, array &$output, array &$ranges): ?array
    {
        usort($ranges, static fn (array $left, array $right): int =>
            ((int) $left['outputStart'] <=> (int) $right['outputStart'])
                ?: ((int) $left['outputEnd'] <=> (int) $right['outputEnd'])
        );
        $cursor = 0;
        foreach ($ranges as $range) {
            if ((int) $range['outputStart'] !== $cursor
                || (int) $range['outputEnd'] <= (int) $range['outputStart']) {
                return null;
            }
            $cursor = (int) $range['outputEnd'];
        }
        if ($cursor !== strlen($output['text'])) {
            return null;
        }
        unset($output['text']);

        $blockEdges = self::sourceBindingIntersectionsForDestinations(
            $ranges,
            $output['blocks'],
            'index'
        );
        $leafEdges = self::sourceBindingIntersectionsForDestinations(
            $ranges,
            $output['leaves'],
            'path'
        );
        $ranges = [];
        unset($output['leaves']);
        foreach ($blockEdges as &$edges) {
            $edges = is_array($edges) ? self::normalizedSourceBindingEdges($edges) : [];
        }
        unset($edges);
        foreach ($leafEdges as &$edges) {
            $edges = is_array($edges) ? self::normalizedSourceBindingEdges($edges) : [];
        }
        unset($edges);

        foreach ($blocks as $blockIndex => $block) {
            $edges = $blockEdges[$blockIndex] ?? [];
            $blockRange = $output['blocks'][$blockIndex] ?? null;
            $hasSignificantText = is_array($blockRange)
                && (int) ($blockRange['end'] ?? 0) > (int) ($blockRange['start'] ?? 0);
            if ($hasSignificantText && $edges === []) {
                return null;
            }
            // Media/placeholders and other genuinely textless AST blocks are
            // not destinations for a text-line occurrence. They retain their
            // own visual/structural provenance and must not make an otherwise
            // exact source-to-text edge graph incomplete.
            if (!$hasSignificantText) {
                continue;
            }
        }
        $output = [];

        $mappingBySourceId = [];
        $decorated = [];
        foreach ($blocks as $blockIndex => $block) {
            $edges = $blockEdges[$blockIndex] ?? [];
            $publicBlockEdges = self::publicSourceBindingEdges($edges);
            $topNodeId = $publicBlockEdges === []
                ? ''
                : 'pdf-source-node-' . substr(
                    self::sourceBindingTopNodeIdentityDigest(
                        $block->type,
                        $publicBlockEdges
                    ),
                    0,
                    32
                );
            // The public top-level edge list and the mapping entries below are
            // the only remaining owners needed for this block. Drop its
            // larger private intersection bucket before recursively
            // decorating a dense descendant tree.
            unset($blockEdges[$blockIndex]);
            $inlineIdsBySource = [];
            $decorated[] = $block->type === 'table'
                && self::sourceBindingTableTextLeavesAreCellScoped($block)
                ? self::decoratedCompactTableSourceBindingNode(
                    $block,
                    (string) $blockIndex,
                    $topNodeId,
                    $publicBlockEdges,
                    $leafEdges,
                    $inlineIdsBySource
                )
                : self::decoratedSourceBindingNode(
                    $block,
                    (string) $blockIndex,
                    true,
                    $topNodeId,
                    $publicBlockEdges,
                    $leafEdges,
                    $inlineIdsBySource
                );
            foreach ($edges as $edge) {
                $sourceId = $edge['sourceLineId'];
                if (!isset($mappingBySourceId[$sourceId])) {
                    $mappingBySourceId[$sourceId] = [
                        'destinationNodeIds' => [],
                        'destinationInlineIds' => [],
                        'mappingMode' => 'exact-sequence',
                        'scopeId' => null,
                        '_scopeIdConflict' => false,
                    ];
                }
                $lastNodeIndex = array_key_last($mappingBySourceId[$sourceId]['destinationNodeIds']);
                if ($lastNodeIndex === null
                    || $mappingBySourceId[$sourceId]['destinationNodeIds'][$lastNodeIndex] !== $topNodeId) {
                    $mappingBySourceId[$sourceId]['destinationNodeIds'][] = $topNodeId;
                }
                if (($edge['mappingMode'] ?? null) === 'exact-authorized-scope') {
                    $mappingBySourceId[$sourceId]['mappingMode'] = 'exact-authorized-scope';
                }
                if (is_string($edge['scopeId'] ?? null) && $edge['scopeId'] !== '') {
                    $scopeId = $edge['scopeId'];
                    if ($mappingBySourceId[$sourceId]['_scopeIdConflict'] !== true) {
                        $currentScopeId = $mappingBySourceId[$sourceId]['scopeId'];
                        if ($currentScopeId === null) {
                            $mappingBySourceId[$sourceId]['scopeId'] = $scopeId;
                        } elseif ($currentScopeId !== $scopeId) {
                            $mappingBySourceId[$sourceId]['scopeId'] = null;
                            $mappingBySourceId[$sourceId]['_scopeIdConflict'] = true;
                        }
                    }
                }
            }
            foreach ($inlineIdsBySource as $sourceId => $inlineIds) {
                foreach ($inlineIds as $inlineId) {
                    $mappingBySourceId[$sourceId]['destinationInlineIds'][] = $inlineId;
                }
            }
            unset($edges, $publicBlockEdges, $inlineIdsBySource);
        }
        // The decorated AST and occurrence mapping now own the public edge
        // data. Release the larger private intersection graphs before
        // compacting mapping sets; dense table PDFs otherwise retain both
        // representations at the highest-memory point of a successful bind.
        unset($blockEdges, $leafEdges, $inlineIdsBySource);

        foreach ($mappingBySourceId as &$mapping) {
            unset($mapping['_scopeIdConflict']);
        }
        unset($mapping);

        return ['blocks' => $decorated, 'mappingBySourceId' => $mappingBySourceId];
    }

    /**
     * Hash the exact JSON identity without materializing its complete edge
     * list as a second string.
     *
     * @param list<array{sourceLineId:string,startByte:int,endByte:int}> $edges
     */
    private static function sourceBindingTopNodeIdentityDigest(
        string $type,
        array $edges
    ): string {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        $encodedType = json_encode($type, $flags);
        if (!is_string($encodedType)) {
            return hash('sha256', serialize(['type' => $type, 'sourceLineEdges' => $edges]));
        }
        $hash = hash_init('sha256');
        hash_update($hash, '{"type":' . $encodedType . ',"sourceLineEdges":[');
        foreach ($edges as $index => $edge) {
            $encodedEdge = json_encode($edge, $flags);
            if (!is_string($encodedEdge)) {
                return hash('sha256', serialize(['type' => $type, 'sourceLineEdges' => $edges]));
            }
            if ($index > 0) {
                hash_update($hash, ',');
            }
            hash_update($hash, $encodedEdge);
        }
        hash_update($hash, ']}');

        return hash_final($hash);
    }

    /**
     * Translate prevalidated semantic marker paths to the immutable IDs on
     * the decorated list and list-item nodes.
     *
     * @param list<AstNode> $blocks
     * @param array<string,array{blockIndex:int,itemIndex:int}> $targetsBySourceId
     * @return array{mappingBySourceId:array<string,array<string,mixed>>,failureReason:?string}
     */
    public static function semanticStructureMappingsFromPlan(
        array $blocks,
        array $targetsBySourceId
    ): array {
        $mappings = [];
        foreach ($targetsBySourceId as $sourceId => $target) {
            $blockIndex = $target['blockIndex'];
            $itemIndex = $target['itemIndex'];
            $list = $blocks[$blockIndex] ?? null;
            $items = $list instanceof AstNode ? $list->children() : [];
            $item = $items[$itemIndex] ?? null;
            $listNodeId = $list instanceof AstNode
                ? $list->attr('sourceNodeId')
                : null;
            $itemNodeId = $item instanceof AstNode
                ? $item->attr('sourceNodeId')
                : null;
            if (!$list instanceof AstNode
                || !in_array($list->type, ['ordered_list', 'bullet_list'], true)
                || !$item instanceof AstNode
                || $item->type !== 'list_item'
                || !is_string($listNodeId)
                || $listNodeId === ''
                || !is_string($itemNodeId)
                || $itemNodeId === '') {
                return [
                    'mappingBySourceId' => [],
                    'failureReason' =>
                        'semantic-list-marker-has-no-unique-structural-target',
                ];
            }
            $mappings[$sourceId] = [
                'destinationNodeIds' => [$listNodeId],
                'destinationInlineIds' => [$itemNodeId],
                'mappingMode' => self::SEMANTIC_STRUCTURE_MAPPING_MODE,
                'scopeId' => null,
            ];
        }

        return ['mappingBySourceId' => $mappings, 'failureReason' => null];
    }

    public static function nodeHasSourceLineEdge(AstNode $node, string $sourceId): bool
    {
        $edges = $node->attr('sourceLineEdges', []);
        if (is_array($edges)) {
            foreach ($edges as $edge) {
                if (is_array($edge) && ($edge['sourceLineId'] ?? null) === $sourceId) {
                    return true;
                }
            }
        }
        foreach ($node->children() as $child) {
            if (self::nodeHasSourceLineEdge($child, $sourceId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string,mixed>>|null $edges
     * @param array<string,mixed> $range
     * @param array<string,mixed> $destinationRange
     */
    private static function appendSourceBindingIntersection(
        ?array &$edges,
        array $range,
        array $destinationRange
    ): void {
        $start = max((int) $range['outputStart'], (int) $destinationRange['start']);
        $end = min((int) $range['outputEnd'], (int) $destinationRange['end']);
        if ($end <= $start) {
            return;
        }
        $edges ??= [];
        $sourceStart = (int) $range['sourceStart'] + ($start - (int) $range['outputStart']);
        $edges[] = [
            'sourceLineId' => $range['sourceOccurrenceId'],
            'startByte' => $sourceStart,
            'endByte' => $sourceStart + ($end - $start),
            'outputStart' => $start,
            'outputEnd' => $end,
            'mappingMode' => $range['mappingMode'],
            'scopeId' => $range['scopeId'],
        ];
    }

    /**
     * Intersect two output-ordered range lists without rescanning every AST
     * destination for every source span. A source span may cross adjacent
     * destinations, so retain the current source index until its end has been
     * consumed by a later destination.
     *
     * @param list<array<string,mixed>> $ranges
     * @param list<array<string,mixed>> $destinations
     * @return array<int|string,list<array<string,mixed>>>
     */
    private static function sourceBindingIntersectionsForDestinations(
        array $ranges,
        array $destinations,
        string $destinationKey
    ): array {
        $edgesByDestination = [];
        $rangeIndex = 0;
        $rangeCount = count($ranges);
        foreach ($destinations as $destination) {
            $destinationStart = (int) ($destination['start'] ?? 0);
            $destinationEnd = (int) ($destination['end'] ?? 0);
            $key = $destination[$destinationKey] ?? null;
            if ((!is_int($key) && !is_string($key))
                || $destinationEnd <= $destinationStart) {
                continue;
            }
            while ($rangeIndex < $rangeCount
                && (int) $ranges[$rangeIndex]['outputEnd'] <= $destinationStart) {
                $rangeIndex++;
            }
            $candidateIndex = $rangeIndex;
            while ($candidateIndex < $rangeCount
                && (int) $ranges[$candidateIndex]['outputStart'] < $destinationEnd) {
                self::appendSourceBindingIntersection(
                    $edgesByDestination[$key],
                    $ranges[$candidateIndex],
                    $destination
                );
                if ((int) $ranges[$candidateIndex]['outputEnd'] > $destinationEnd) {
                    break;
                }
                $candidateIndex++;
            }
            $rangeIndex = $candidateIndex;
        }

        return $edgesByDestination;
    }

    /** @param list<array<string,mixed>> $edges @return list<array<string,mixed>> */
    private static function normalizedSourceBindingEdges(array $edges): array
    {
        usort($edges, static fn (array $left, array $right): int =>
            ((int) $left['outputStart'] <=> (int) $right['outputStart'])
                ?: ((int) $left['outputEnd'] <=> (int) $right['outputEnd'])
        );
        $normalized = [];
        foreach ($edges as $edge) {
            $lastIndex = array_key_last($normalized);
            $last = $lastIndex === null ? null : $normalized[$lastIndex];
            if (is_array($last)
                && $last['sourceLineId'] === $edge['sourceLineId']
                && $last['mappingMode'] === $edge['mappingMode']
                && $last['scopeId'] === $edge['scopeId']
                && $last['endByte'] === $edge['startByte']
                && $last['outputEnd'] === $edge['outputStart']) {
                $normalized[$lastIndex]['endByte'] = $edge['endByte'];
                $normalized[$lastIndex]['outputEnd'] = $edge['outputEnd'];
                continue;
            }
            $normalized[] = $edge;
        }

        return $normalized;
    }

    /** @param list<array<string,mixed>> $edges @return list<array{sourceLineId:string,startByte:int,endByte:int}> */
    private static function publicSourceBindingEdges(array $edges): array
    {
        return array_map(
            static fn (array $edge): array => [
                'sourceLineId' => (string) $edge['sourceLineId'],
                'startByte' => (int) $edge['startByte'],
                'endByte' => (int) $edge['endByte'],
            ],
            $edges
        );
    }

    /**
     * @param list<array{sourceLineId:string,startByte:int,endByte:int}> $topBlockEdges
     * @param array<string,list<array<string,mixed>>> $leafEdges
     * @param array<string,list<string>> $inlineIdsBySource
     */
    private static function decoratedSourceBindingNode(
        AstNode $node,
        string $path,
        bool $topLevel,
        string $topNodeId,
        array $topBlockEdges,
        array &$leafEdges,
        array &$inlineIdsBySource
    ): AstNode {
        $children = [];
        $edges = [];
        $edgesArePublic = false;
        if ($node->type === 'text' || $node->type === 'code_block') {
            $edges = $leafEdges[$path] ?? [];
            unset($leafEdges[$path]);
        } else {
            $uniquePublicEdges = [];
            foreach ($node->children() as $childIndex => $child) {
                $childPath = $path . '.' . $childIndex;
                $decoratedChild = self::decoratedSourceBindingNode(
                    $child,
                    $childPath,
                    false,
                    $topNodeId,
                    $topBlockEdges,
                    $leafEdges,
                    $inlineIdsBySource
                );
                $children[] = $decoratedChild;
                if (!$topLevel) {
                    foreach ($decoratedChild->attr('sourceLineEdges', []) as $edge) {
                        if (!is_array($edge)) {
                            continue;
                        }
                        // Child edges are already the immutable public shape.
                        // Reuse those inner arrays and deduplicate by their
                        // exact occurrence span instead of expanding each one
                        // back to the larger private intersection shape.
                        $key = $edge['sourceLineId'] . ':' . $edge['startByte'] . ':' . $edge['endByte'];
                        $uniquePublicEdges[$key] = $edge;
                    }
                }
            }
            if (!$topLevel) {
                $edges = array_values($uniquePublicEdges);
                $edgesArePublic = true;
            }
        }
        if ($topLevel) {
            $edges = $topBlockEdges;
            $edgesArePublic = true;
        }

        $attrs = self::sourceBindingAttrsWithoutDecoration($node);
        if ($edges !== []) {
            $publicEdges = $edgesArePublic ? $edges : self::publicSourceBindingEdges($edges);
            $sourceLineIds = self::sourceBindingSourceLineIds($publicEdges);
            $nodeId = $topLevel
                ? $topNodeId
                : 'pdf-source-inline-' . substr(hash(
                    'sha256',
                    $topNodeId . "\0" . $path . "\0" . serialize($publicEdges)
                ), 0, 32);
            $attrs['sourceNodeId'] = $nodeId;
            $attrs['sourceLineIds'] = $sourceLineIds;
            $attrs['sourceLineEdges'] = $publicEdges;
            if (!$topLevel) {
                foreach ($sourceLineIds as $sourceId) {
                    $inlineIdsBySource[$sourceId][] = $nodeId;
                }
            }
        }

        return new AstNode($node->type, $attrs, $children);
    }

    /**
     * Dense PDF tables can contain tens of thousands of text leaves. Retaining
     * the same exact edge at text, formatting, plain, row, section, cell, and
     * table levels multiplies immutable provenance without adding a distinct
     * semantic destination. Use the table and each nonempty table cell as the
     * canonical provenance boundaries; lists and every non-table tree keep
     * their full decoration so semantic list-marker binding is unchanged.
     *
     * @param list<array{sourceLineId:string,startByte:int,endByte:int}> $topBlockEdges
     * @param array<string,list<array<string,mixed>>> $leafEdges
     * @param array<string,list<string>> $inlineIdsBySource
     */
    private static function decoratedCompactTableSourceBindingNode(
        AstNode $node,
        string $path,
        string $topNodeId,
        array $topBlockEdges,
        array &$leafEdges,
        array &$inlineIdsBySource
    ): AstNode {
        $children = [];
        foreach ($node->children() as $childIndex => $child) {
            $children[] = self::decoratedCompactTableStructureNode(
                $child,
                $path . '.' . $childIndex,
                $topNodeId,
                $leafEdges,
                $inlineIdsBySource
            );
        }

        $attrs = self::sourceBindingAttrsWithoutDecoration($node);
        if ($topBlockEdges !== []) {
            $attrs['sourceNodeId'] = $topNodeId;
            $attrs['sourceLineIds'] = self::sourceBindingSourceLineIds($topBlockEdges);
            $attrs['sourceLineEdges'] = $topBlockEdges;
        }

        return new AstNode($node->type, $attrs, $children);
    }

    /**
     * Rebuild table structure without redundant provenance on section and row
     * wrappers. A cell consumes all exact leaf edges in its own subtree.
     *
     * @param array<string,list<array<string,mixed>>> $leafEdges
     * @param array<string,list<string>> $inlineIdsBySource
     */
    private static function decoratedCompactTableStructureNode(
        AstNode $node,
        string $path,
        string $topNodeId,
        array &$leafEdges,
        array &$inlineIdsBySource
    ): AstNode {
        if ($node->type === 'table_cell') {
            return self::decoratedCompactTableCell(
                $node,
                $path,
                $topNodeId,
                $leafEdges,
                $inlineIdsBySource
            );
        }

        $children = [];
        foreach ($node->children() as $childIndex => $child) {
            $children[] = self::decoratedCompactTableStructureNode(
                $child,
                $path . '.' . $childIndex,
                $topNodeId,
                $leafEdges,
                $inlineIdsBySource
            );
        }

        return new AstNode(
            $node->type,
            self::sourceBindingAttrsWithoutDecoration($node),
            $children
        );
    }

    /**
     * @param array<string,list<array<string,mixed>>> $leafEdges
     * @param array<string,list<string>> $inlineIdsBySource
     */
    private static function decoratedCompactTableCell(
        AstNode $node,
        string $path,
        string $topNodeId,
        array &$leafEdges,
        array &$inlineIdsBySource
    ): AstNode {
        $uniquePublicEdges = [];
        $children = [];
        foreach ($node->children() as $childIndex => $child) {
            $children[] = self::decoratedCompactTableCellContentNode(
                $child,
                $path . '.' . $childIndex,
                $leafEdges,
                $uniquePublicEdges
            );
        }

        $attrs = self::sourceBindingAttrsWithoutDecoration($node);
        $publicEdges = array_values($uniquePublicEdges);
        if ($publicEdges !== []) {
            $sourceLineIds = self::sourceBindingSourceLineIds($publicEdges);
            $nodeId = 'pdf-source-inline-' . substr(hash(
                'sha256',
                $topNodeId . "\0" . $path . "\0" . serialize($publicEdges)
            ), 0, 32);
            $attrs['sourceNodeId'] = $nodeId;
            $attrs['sourceLineIds'] = $sourceLineIds;
            $attrs['sourceLineEdges'] = $publicEdges;
            foreach ($sourceLineIds as $sourceId) {
                $inlineIdsBySource[$sourceId][] = $nodeId;
            }
        }

        return new AstNode($node->type, $attrs, $children);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $leafEdges
     * @param array<string,array{sourceLineId:string,startByte:int,endByte:int}> $uniquePublicEdges
     */
    private static function decoratedCompactTableCellContentNode(
        AstNode $node,
        string $path,
        array &$leafEdges,
        array &$uniquePublicEdges
    ): AstNode {
        $children = [];
        if ($node->type === 'text' || $node->type === 'code_block') {
            $edges = $leafEdges[$path] ?? [];
            unset($leafEdges[$path]);
            foreach (self::publicSourceBindingEdges($edges) as $edge) {
                $key = $edge['sourceLineId'] . ':' . $edge['startByte'] . ':' . $edge['endByte'];
                $uniquePublicEdges[$key] = $edge;
            }
        } else {
            foreach ($node->children() as $childIndex => $child) {
                $children[] = self::decoratedCompactTableCellContentNode(
                    $child,
                    $path . '.' . $childIndex,
                    $leafEdges,
                    $uniquePublicEdges
                );
            }
        }

        return new AstNode(
            $node->type,
            self::sourceBindingAttrsWithoutDecoration($node),
            $children
        );
    }

    private static function sourceBindingTableTextLeavesAreCellScoped(
        AstNode $node,
        bool $insideCell = false
    ): bool {
        $insideCell = $insideCell || $node->type === 'table_cell';
        if ($node->type === 'text' || $node->type === 'code_block') {
            return $insideCell || self::significantText((string) $node->attr('text', '')) === '';
        }
        foreach ($node->children() as $child) {
            if (!self::sourceBindingTableTextLeavesAreCellScoped($child, $insideCell)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string,mixed> */
    private static function sourceBindingAttrsWithoutDecoration(AstNode $node): array
    {
        // Resolve directly into the array which the replacement node will
        // own. Reading the public attrs property would also cache this array
        // on the old node and force a second hash-table allocation as soon as
        // provenance attributes are added below.
        $attrs = $node->baseAttrs();
        $resolver = $node->attributeResolver();
        if ($resolver !== null) {
            $attrs = $resolver->materialize($attrs, $node);
        }
        foreach (['sourceNodeId', 'sourceLineIds', 'sourceLineEdges'] as $key) {
            unset($attrs[$key]);
        }

        return $attrs;
    }

    /**
     * @param list<array{sourceLineId:string,startByte:int,endByte:int}> $publicEdges
     * @return list<string>
     */
    private static function sourceBindingSourceLineIds(array $publicEdges): array
    {
        $sourceIds = [];
        foreach ($publicEdges as $edge) {
            $sourceId = $edge['sourceLineId'];
            $lastIndex = array_key_last($sourceIds);
            if (($lastIndex === null || $sourceIds[$lastIndex] !== $sourceId)
                && !in_array($sourceId, $sourceIds, true)) {
                $sourceIds[] = $sourceId;
            }
        }

        return $sourceIds;
    }
}
