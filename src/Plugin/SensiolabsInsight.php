<?php

namespace PHPCensor\Plugin;

use PHPCensor;
use PHPCensor\Builder;
use PHPCensor\Model\Build;
use PHPCensor\Plugin;

/**
 * Sensiolabs Insight Plugin - Allows Sensiolabs Insight testing.
 *
 * @author Eugen Ganshorn <eugen@ganshorn.eu>
 */
class SensiolabsInsight extends Plugin
{
    /**
     * @var string
     */
    protected $userUuid;

    /**
     * @var string
     */
    protected $apiToken;

    /**
     * @var string
     */
    protected $projectUuid;

    /**
     * @var int
     */
    protected $allowedWarnings;

    /**
     * @return string
     */
    public static function pluginName()
    {
        return 'sensiolabs_insight';
    }

    /**
     * {@inheritdoc}
     */
    public function __construct(Builder $builder, Build $build, array $options = [])
    {
        parent::__construct($builder, $build, $options);

        $this->allowedWarnings = 0;
        if (array_key_exists('allowed_warnings', $options)) {
            $this->allowedWarnings = (int)$options['allowed_warnings'];
        }

        if (array_key_exists('user_uuid', $options)) {
            $this->userUuid = $options['user_uuid'];
        }

        if (array_key_exists('api_token', $options)) {
            $this->apiToken = $options['api_token'];
        }

        if (array_key_exists('project_uuid', $options)) {
            $this->projectUuid = $options['project_uuid'];
        }
    }

    /**
     * Runs Sensiolabs Insights in a specified directory.
     *
     * @throws \Exception
     */
    public function execute()
    {
        $insightBinaryPath = $this->findBinary('insight');

        $this->executeSensiolabsInsight($insightBinaryPath);

        $errorCount = $this->processReport(trim($this->builder->getLastOutput()));
        $this->build->storeMeta('sensiolabs_insight-warnings', $errorCount);

        return $this->wasLastExecSuccessful($errorCount);
    }

    /**
     * Process PHPMD's XML output report.
     *
     * @param $xmlString
     *
     * @return integer
     *
     * @throws \Exception
     */
    protected function processReport($xmlString)
    {
        $xml = simplexml_load_string($xmlString);

        if ($xml === false) {
            $this->builder->log($xmlString);
            throw new \RuntimeException('Could not process PHPMD report XML.');
        }

        $warnings = 0;

        foreach ($xml->file as $file) {
            $fileName = (string)$file['name'];
            $fileName = str_replace($this->builder->buildPath, '', $fileName);

            foreach ($file->violation as $violation) {
                $warnings++;

                $this->build->reportError(
                    $this->builder,
                    'sensiolabs_insight',
                    (string)$violation,
                    PHPCensor\Model\BuildError::SEVERITY_HIGH,
                    $fileName,
                    (int)$violation['beginline'],
                    (int)$violation['endline']
                );
            }
        }

        return $warnings;
    }

    /**
     * Execute Sensiolabs Insight.
     * @param $binaryPath
     */
    protected function executeSensiolabsInsight($binaryPath)
    {
        $cmd = $binaryPath . ' -n analysis --format pmd %s --api-token %s --user-uuid %s';

        // Disable exec output logging, as we don't want the XML report in the log:
        $this->builder->logExecOutput(false);

        // Run Sensiolabs Insight:
        $this->builder->executeCommand(
            $cmd,
            $this->projectUuid,
            $this->apiToken,
            $this->userUuid
        );

        // Re-enable exec output logging:
        $this->builder->logExecOutput(true);
    }

    /**
     * Returns a boolean indicating if the error count can be considered a success.
     *
     * @param int $errorCount
     * @return bool
     */
    protected function wasLastExecSuccessful($errorCount)
    {
        $success = true;

        if ($this->allowedWarnings !== -1 && $errorCount > $this->allowedWarnings) {
            $success = false;
            return $success;
        }
        return $success;
    }
}
