<?php

declare(strict_types=1);

namespace Shopsys\Releaser\ReleaseWorker;

use PharIo\Version\Version;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symplify\MonorepoBuilder\FileSystem\ComposerJsonProvider;
use Symplify\MonorepoBuilder\InterdependencyUpdater;
use Symplify\MonorepoBuilder\Package\PackageNamesProvider;
use Symplify\MonorepoBuilder\Release\Contract\ReleaseWorker\ReleaseWorkerInterface;
use Symplify\MonorepoBuilder\Release\Message;
use Symplify\MonorepoBuilder\Utils\Utils;

final class SetMutualDependenciesToVersionReleaseWorker implements ReleaseWorkerInterface
{
    /**
     * @var \Symfony\Component\Console\Style\SymfonyStyle
     */
    private $symfonyStyle;

    /**
     * @var \Symplify\MonorepoBuilder\FileSystem\ComposerJsonProvider
     */
    private $composerJsonProvider;

    /**
     * @var \Symplify\MonorepoBuilder\InterdependencyUpdater
     */
    private $interdependencyUpdater;

    /**
     * @var \Symplify\MonorepoBuilder\Utils\Utils
     */
    private $utils;

    /**
     * @var \Symplify\MonorepoBuilder\Package\PackageNamesProvider
     */
    private $packageNamesProvider;

    /**
     * @param \Symfony\Component\Console\Style\SymfonyStyle $symfonyStyle
     * @param \Symplify\MonorepoBuilder\FileSystem\ComposerJsonProvider $composerJsonProvider
     * @param \Symplify\MonorepoBuilder\InterdependencyUpdater $interdependencyUpdater
     * @param \Symplify\MonorepoBuilder\Utils\Utils $utils
     * @param \Symplify\MonorepoBuilder\Package\PackageNamesProvider $packageNamesProvider
     */
    public function __construct(SymfonyStyle $symfonyStyle, ComposerJsonProvider $composerJsonProvider, InterdependencyUpdater $interdependencyUpdater, Utils $utils, PackageNamesProvider $packageNamesProvider)
    {
        $this->symfonyStyle = $symfonyStyle;
        $this->composerJsonProvider = $composerJsonProvider;
        $this->interdependencyUpdater = $interdependencyUpdater;
        $this->utils = $utils;
        $this->packageNamesProvider = $packageNamesProvider;
    }

    /**
     * @param \PharIo\Version\Version $version
     * @return string
     */
    public function getDescription(Version $version): string
    {
        $requiredVersionInString = $this->utils->getRequiredFormat($version);

        return sprintf('Set mutual package dependencies to "%s" version', $requiredVersionInString);
    }

    /**
     * Higher first
     * @return int
     */
    public function getPriority(): int
    {
        return 760;
    }

    /**
     * @param \PharIo\Version\Version $version
     */
    public function work(Version $version): void
    {
        $requiredVersionInString = $this->utils->getRequiredFormat($version);

        $this->interdependencyUpdater->updateFileInfosWithPackagesAndVersion(
            $this->composerJsonProvider->getPackagesFileInfos(),
            $this->packageNamesProvider->provide(),
            $requiredVersionInString
        );

        $this->symfonyStyle->success(Message::SUCCESS);
    }
}
