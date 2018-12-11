<?php

declare(strict_types=1);

namespace Shopsys\Releaser\ReleaseWorker;

use PharIo\Version\Version;
use Symplify\MonorepoBuilder\Release\Contract\ReleaseWorker\ReleaseWorkerInterface;

final class StopMergingToMasterReleaseWorker implements ReleaseWorkerInterface
{
    /**
     * @param \PharIo\Version\Version $version
     * @return string
     */
    public function getDescription(Version $version): string
    {
        return '[Manual] Tell team to stop merging to `master` branch';
    }

    /**
     * Higher first
     * @return int
     */
    public function getPriority(): int
    {
        return 940;
    }

    /**
     * @param \PharIo\Version\Version $version
     */
    public function work(Version $version): void
    {
    }
}
