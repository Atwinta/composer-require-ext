<?php

namespace Lira\ComposerExt;

use ReflectionObject;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Composer\Repository\VcsRepository;
use Composer\Json\JsonFile;
use Composer\Factory;

class RequireExtCommand extends \Composer\Command\RequireCommand
{

    protected $pluginName = 'lira/composer-ext';
    protected $addInsecure = false;
    protected $downloader = null;

    public function configure()
    {
        parent::configure();
        $this->setName('require-ext');
        $this->setDescription('Extended versiobn of "require" command provided by ' . $this->pluginName);

        $this->getDefinition()->addOption(
                new InputOption('from-repo', 'F', InputOption::VALUE_OPTIONAL, 'Seach for package in a specific VCS repositoy')
        );

        $this->getDefinition()->addOption(
                new InputOption('insecure', 'K', InputOption::VALUE_NONE, 'Set secure-http=false to composer.json if any http:// URL have been given')
        );
    }

    protected function getDownloader()
    {
        if ($this->downloader === null) {
            $rm = $this->getComposer()->getRepositoryManager();
            $property = (new ReflectionObject($rm))->getProperty('httpDownloader');
            $property->setAccessible(true);
            $this->downloader = $property->getValue($rm);
        }
        return $this->downloader;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('insecure')) {
            $this->addInsecure = true;
            $conf = $this->getComposer()->getConfig();
            $conf->merge(['config' => ['secure-http' => false]]);
            $this->getComposer()->setConfig($conf);
        }

        $packages = $input->getArgument('packages');
        $repo_urls = array_filter($packages, [__CLASS__, 'isVcsUrl']);
        $packages = array_filter($packages, [__CLASS__, 'isNotVcsUrl']);

        list( $more_packages, $more_repos ) = $this->getPackageSpecFromVcsRepos($input, $output, $repo_urls);

        $packages = array_merge($packages, $more_packages);

        $from = $input->getOption('from-repo');
        if ($from && self::isVcsUrl($from)) {
            $more_repos[] = new VcsRepository(
                    ['url' => $from], $this->getIO(), $this->getComposer()->getConfig(), $this->getDownloader()
            );
        }

        if (count($more_repos) > 0) {
            $this->addRepositoriesIfNotExists($more_repos);
        }

        $input->setArgument('packages', $packages);
        return parent::execute($input, $output);
    }

    /**
     *
     * @param string $url
     * @return boolean
     */
    public static function isVcsUrl($url)
    {
        if ('https://' === substr($url, 0, 8) || 'http://' === substr($url, 0, 7)) {
            if ('.git' === substr($url, -4)) {
                return true;
            }
        } else {
            if ('ssh://' === substr($url, 0, 6)) {
                return true;
            }
            if ('git@' === substr($url, 0, 4)) {
                return true;
            }
        }
        return false;
    }

    public static function isNotVcsUrl($url)
    {
        return !self::isVcsUrl($url);
    }

    /**
     *
     * @param string $url
     * @return boolean
     */
    public static function isInsecureUrl($url)
    {
        return 'http://' === substr($url, 0, 7);
    }

    /**
     *
     * @param type $url
     * @return string
     */
    public static function getVcsType($url)
    {
        if ('https://' === substr($url, 0, 8) || 'http://' === substr($url, 0, 7)) {
            if ('.git' === substr($url, -4)) {
                return 'git';
            }
        } else {
            if ('ssh://' === substr($url, 0, 6)) {
                return 'hg';
            }
            if ('git@' === substr($url, 0, 4)) {
                return 'git';
            }
        }
        return 'vcs';
    }

    /**
     * Пробуем найти в репозитории пакет
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param string[] $urls
     * @return array
     */
    protected function getPackageSpecFromVcsRepos(InputInterface $input, OutputInterface $output, array $urls)
    {
        $packages = [];
        $repos = [];
        $io = $this->getIO();
        $config = $this->getComposer()->getConfig();
        $recur_repo = [];
        foreach ($urls as $url) {
            $repo = new VcsRepository(
                    ['url' => $url], $io, $config, $this->getDownloader()
            );
            $any = false;
            // repo's composer.json data:
            $repoComposer = $repo->getDriver()->getComposerInformation($repo->getDriver()->getRootIdentifier());
            if (!empty($repoComposer['repositories'])) {
                foreach ($repoComposer['repositories'] as $subr) {
                    $recur_repo[] = $subr['url'];
                }
            }
            foreach ($repo->getPackages() as $p) {
                $packages[] = $p->getName();
                $any = true;
                break;
            }
            if ($any) {
                $repos[$url] = $repo;
            }
        }

        if (count($recur_repo) > 0) {
            // recursively adding repos...
            list($subpakages, $subrepos) = $this->getPackageSpecFromVcsRepos($input, $output, $recur_repo);
            foreach ($subrepos as $subrepo) {
                $c = $subrepo->getRepoConfig();
                if (!array_key_exists($c['url'], $repos)) {
                    $repos[$c['url']] = $subrepo;
                }
            }
            //$repos = array_merge($repos, $subrepos);
        }
        return [$packages, $repos];
    }

    /**
     *
     * @param VcsRepository[] $repos
     */
    protected function addRepositoriesIfNotExists(array $repos)
    {
        $unique_urls = array_map(function (VcsRepository $x) {
            $conf = $x->getRepoConfig();
            return $conf["url"];
        }, $repos);
        $unique_urls = array_unique($unique_urls);
        if (count($unique_urls) === 0) {
            return;
        }

        $file = Factory::getComposerFile();
        $newlyCreated = !file_exists($file);

        $json = new JsonFile($file);
        $composerDefinition = $json->read();
        if (empty($composerDefinition['repositories'])) {
            $composerDefinition['repositories'] = [];
        }

        $isNew = function ($url) use ($composerDefinition) {
            foreach ($composerDefinition['repositories'] as $repo) {
                if (isset($repo['type']) && isset($repo['url'])) {
                    if ($repo['url'] === $url) {
                        return false;
                    }
                }
            }
            return true;
        };

        $changed = false;
        $hasInsecureUrls = false;
        foreach ($repos as $repo) {
            $conf = $repo->getRepoConfig();
            $url = $conf["url"];
            if ($isNew($url)) {
                $hasInsecureUrls = $hasInsecureUrls || self::isInsecureUrl($url);
                $this->getIO()->writeError('Adding new repository: <info>' . $url . '</info>');
                $this->getComposer()->getRepositoryManager()->addRepository($repo);
                $composerDefinition['repositories'][] = [
                    'type' => self::getVcsType($url),
                    'url' => $url,
                ];
                $changed = true;
            }
        }

        if ($changed) {
            if ($hasInsecureUrls && $this->addInsecure) {
                $hasOpt = isset($composerDefinition['config']['secure-http']);
                $hasOpt = $hasOpt && ($composerDefinition['config']['secure-http'] === false);
                if (!$hasOpt) {
                    $this->getIO()->writeError('Adding config option: <info>"secure-http=false"</info> because plain http:// repository is used.');
                    $composerDefinition['config']['secure-http'] = false;
                }
            }
            $json->write($composerDefinition);
            $this->getIO()->writeError('<info>' . $file . ' has been ' . ($newlyCreated ? 'created' : 'modified') . ' by ' . $this->pluginName . '</info>');
        }
    }
}
