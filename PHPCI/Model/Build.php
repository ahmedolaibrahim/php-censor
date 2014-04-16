<?php
/**
* PHPCI - Continuous Integration for PHP
*
* @copyright    Copyright 2013, Block 8 Limited.
* @license      https://github.com/Block8/PHPCI/blob/master/LICENSE.md
* @link         http://www.phptesting.org/
*/

namespace PHPCI\Model;

use b8\Store\Factory;
use PHPCI\Model\Base\BuildBase;
use PHPCI\Builder;
use Symfony\Component\Yaml\Parser as YamlParser;

/**
* Build Model
* @uses         PHPCI\Model\Base\BuildBase
* @author       Dan Cryer <dan@block8.co.uk>
* @package      PHPCI
* @subpackage   Core
*/
class Build extends BuildBase
{
    const STATUS_NEW = 0;
    const STATUS_RUNNING = 1;
    const STATUS_SUCCESS = 2;
    const STATUS_FAILED = 3;

    public $currentBuildPath = null;

    /**
    * Get link to commit from another source (i.e. Github)
    */
    public function getCommitLink()
    {
        return '#';
    }

    /**
     * @return string
     */
    public function getProjectTitle()
    {
        $project = $this->getProject();
        return $project ? $project->getTitle() : "";
    }

    /**
    * Get link to branch from another source (i.e. Github)
    */
    public function getBranchLink()
    {
        return '#';
    }

    /**
    * Send status updates to any relevant third parties (i.e. Github)
    */
    public function sendStatusPostback()
    {
        return;
    }

    /**
     * Store build metadata
     */
    public function storeMeta($key, $value)
    {
        $value = json_encode($value);
        Factory::getStore('Build')->setMeta($this->getProjectId(), $this->getId(), $key, $value);
    }

    /**
     * Is this build successful?
     */
    public function isSuccessful()
    {
        return ($this->getStatus() === self::STATUS_SUCCESS);
    }

    /**
     * @param Builder $builder
     * @param string  $buildPath
     *
     * @return bool
     */
    protected function handleConfig(Builder $builder, $buildPath)
    {
        if (is_file($buildPath . '/phpci.yml')) {
            $build_config = file_get_contents($buildPath . '/phpci.yml');
        }

        if (!is_file($buildPath . '/phpci.yml') || !$build_config) {
            $build_config = $this->getProject()->getBuildConfig();
            if (!$build_config) {
                $builder->logFailure('Project does not contain a phpci.yml file.');
                return false;
            }
        }

        $yamlParser = new YamlParser();
        $builder->setConfigArray($yamlParser->parse($build_config));
        return $builder->getConfig('build_settings');
    }
}
