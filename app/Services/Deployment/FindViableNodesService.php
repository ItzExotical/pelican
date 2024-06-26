<?php

namespace App\Services\Deployment;

use App\Models\Node;
use Illuminate\Support\Collection;

class FindViableNodesService
{
    /**
     * Returns a collection of nodes that meet the provided requirements and can then
     * be passed to the AllocationSelectionService to return a single allocation.
     *
     * This functionality is used for automatic deployments of servers and will
     * attempt to find all nodes in the defined locations that meet the memory, disk
     * and cpu availability requirements. Any nodes not meeting those requirements
     * are tossed out, as are any nodes marked as non-public, meaning automatic
     * deployments should not be done against them.
     */
    public function handle(int $disk = 0, int $memory = 0, int $cpu = 0, $tags = []): Collection
    {
        $nodes = Node::query()
            ->withSum('servers', 'disk')
            ->withSum('servers', 'memory')
            ->withSum('servers', 'cpu')
            ->where('public', true)
            ->get();

        return $nodes
            ->filter(fn (Node $node) => !$tags || collect($node->tags)->intersect($tags))
            ->filter(fn (Node $node) => $node->servers_sum_disk + $disk <= $node->disk * (1 + $node->disk_overallocate / 100))
            ->filter(fn (Node $node) => $node->servers_sum_memory + $memory <= $node->memory * (1 + $node->memory_overallocate / 100))
            ->filter(fn (Node $node) => $node->servers_sum_cpu + $cpu <= $node->cpu * (1 + $node->cpu_overallocate / 100));
    }
}
