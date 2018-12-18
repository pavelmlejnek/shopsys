# Releasing a new version

For releasing a new version, we are leveraging `release` command from [symplify/monorepo-builder](https://github.com/Symplify/MonorepoBuilder) package.

All the source codes and configuration of our release process can be found in `utils/releaser` folder that is located in the root of the monorepo.

Each step of the release process is defined as an implementation of  `Symplify\MonorepoBuilder\Release\Contract\ReleaseWorker\ReleaseWorkerInterface`.

## Stages

The whole release process is divided into 3 stages that are run separately:

1. `release-candidate`
    - the workers are defined in `src/ReleaseWorker/ReleaseCandidate` folder
    - include steps that are done before the release candidate branch is sent to code review and testing
1. `release`
    - the workers are defined in `src/ReleaseWorker/Release` folder
    - include steps that are done during the actual release
1. `after-release`
    - the workers are defined in `src/ReleaseWorker/AfterRelease` folder
    - include steps that are done after the release


## Release command

To perform all the steps of the desired stage, run the following command and follow instructions.
```
vendor/bin/monorepo-builder release <release-number> --stage <stage> -v
```
If you want only to display all the steps of a particular stage, along with the release worker class names, add the `--dry-run` argument:
```
vendor/bin/monorepo-builder release <release-number> --dry-run --stage <stage> -v
```

### Notes
- The "release-number" argument is the desired tag you want to release, it should always follow [the semantic versioning](https://semver.org/)
and start with the "v" prefix, e.g. `v7.0.0-beta5`.
- The releaser needs `.git` folder available - this is a problem currently for our Docker on Mac and Windows configuration
as the folder is currently ignored for performance reasons.
There is [an issue](https://github.com/shopsys/shopsys/issues/536) on Github that mentions the problem.
However, there is a workaround - you can add new `docker-sync` volume just for git.
- Releasing a stage is a continuously running process so do not exit your CLI if it is not necessary.
