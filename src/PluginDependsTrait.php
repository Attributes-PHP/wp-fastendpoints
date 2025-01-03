<?php

declare(strict_types=1);

namespace Wp\FastEndpoints;

trait PluginDependsTrait
{
    /**
     * Plugins required for a given REST endpoint
     *
     * @since 3.0.0
     */
    private ?array $plugins = null;

    /**
     * Specifies a set of plugins that are needed by this router and all sub-routers
     *
     * @return Router|Endpoint|PluginDependsTrait
     */
    public function depends(string|array $plugins): self
    {
        if (is_string($plugins)) {
            $plugins = [$plugins];
        }

        $this->plugins = array_merge($this->plugins ?: [], $plugins);

        return $this;
    }
}
