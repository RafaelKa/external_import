<?php
namespace Cobweb\ExternalImport\Validator;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Cobweb\ExternalImport\DataHandlerInterface;
use Cobweb\ExternalImport\Domain\Model\Configuration;
use Cobweb\ExternalImport\Exception\InvalidCustomStepConfiguration;
use Cobweb\ExternalImport\Importer;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * This class parses the "ctrl" part of an External Import configuration
 * and reports errors and other glitches.
 *
 * NOTE: this is not a strict Extbase Validator.
 *
 * @package Cobweb\ExternalImport\Validator
 */
class ControlConfigurationValidator
{
    /**
     * @var string Name of the table for which the configuration is checked
     */
    protected $table;

    /**
     * @var ValidationResult
     */
    protected $results;

    /**
     * @var \Cobweb\ExternalImport\Utility\StepUtility
     */
    protected $stepUtility;

    public function injectValidationResult(ValidationResult $result)
    {
        $this->results = $result;
    }

    public function injectStepUtility(\Cobweb\ExternalImport\Utility\StepUtility $stepUtility)
    {
        $this->stepUtility = $stepUtility;
    }

    /**
     * Validates the given configuration.
     *
     * @param Configuration $configuration Configuration object to check
     * @return bool
     */
    public function isValid(Configuration $configuration)
    {
        $this->table = $configuration->getTable();
        $ctrlConfiguration = $configuration->getCtrlConfiguration();

        // Validate all properties on which conditions apply
        $this->validateDataProperty($ctrlConfiguration['data']);
        $this->validateConnectorProperty($ctrlConfiguration['connector']);
        $this->validateDataHandlerProperty($ctrlConfiguration['dataHandler']);
        $this->validateReferenceUidProperty($ctrlConfiguration['referenceUid']);
        $this->validatePidProperty($configuration->getStoragePid());
        $this->validateUseColumnIndexProperty(
                $ctrlConfiguration['useColumnIndex'],
                $configuration->getColumnConfiguration()
        );

        // Validate properties for pull-only configurations
        if (!empty($ctrlConfiguration['connector'])) {
            $this->validatePriorityProperty($ctrlConfiguration['priority']);
        }

        // Validate properties specific to the "xml"-type data
        if ($ctrlConfiguration['data'] === 'xml') {
            $this->validateNodeProperty(
                    $ctrlConfiguration['nodetype'],
                    $ctrlConfiguration['nodepath']
            );
        }
        // Return the global validation result
        // Consider that the configuration does not validate if there's at least one error or one warning
        $errorResults = $this->results->getForSeverity(AbstractMessage::ERROR);
        $warningResults = $this->results->getForSeverity(AbstractMessage::WARNING);
        return count($errorResults) + count($warningResults) === 0;
    }

    /**
     * Validates the "data" property.
     *
     * @param string $property Property value
     * @return void
     */
    public function validateDataProperty($property)
    {
        if (empty($property)) {
            $this->results->add(
                    'data',
                    LocalizationUtility::translate(
                            'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:missingDataProperty',
                            'external_import'
                    ),
                    AbstractMessage::ERROR
            );
        } else {
            if ($property !== 'array' && $property !== 'xml') {
                $this->results->add(
                        'data',
                        LocalizationUtility::translate(
                                'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:invalidDataProperty',
                                'external_import'
                        ),
                        AbstractMessage::ERROR
                );
            }
        }
    }

    /**
     * Validates the "connector" property.
     *
     * NOTE: an empty connector is okay, it just means data is being pushed instead of pulled
     * (of course, this may be wrong, but we have no way to guess the user's intent ;-) ).
     *
     * @param string $property Property value
     * @return void
     */
    public function validateConnectorProperty($property)
    {
        if (!empty($property)) {
            $services = ExtensionManagementUtility::findService(
                    'connector',
                    $property
            );
            if ($services === false) {
                $this->results->add(
                        'connector',
                        LocalizationUtility::translate(
                                'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:unavailableConnector',
                                'external_import'
                        ),
                        AbstractMessage::ERROR
                );
            }
        }
    }

    /**
     * Validates the "dataHandler" property.
     *
     * @param string $property Property value
     * @return void
     */
    public function validateDataHandlerProperty($property)
    {
        if (!empty($property)) {
            if (class_exists($property)) {
                try {
                    $dataHandler = GeneralUtility::makeInstance($property);
                    if (!($dataHandler instanceof DataHandlerInterface)) {
                        $this->results->add(
                                'dataHandler',
                                LocalizationUtility::translate(
                                        'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:dataHandlerInterfaceIssue',
                                        'external_import'
                                ),
                                AbstractMessage::NOTICE
                        );
                    }
                }
                catch (\Exception $e) {
                    $this->results->add(
                            'dataHandler',
                            LocalizationUtility::translate(
                                    'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:dataHandlerNoInstance',
                                    'external_import'
                            ),
                            AbstractMessage::NOTICE
                    );
                }
            } else {
                $this->results->add(
                        'dataHandler',
                        LocalizationUtility::translate(
                                'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:dataHandlerNotFound',
                                'external_import'
                        ),
                        AbstractMessage::NOTICE
                );
            }
        }
    }

    /**
     * Validates that there's either "nodetype" or "nodepath" property.
     *
     * @param string $nodetype Nodetype property value
     * @param string $nodepath Nodepath property value
     * @return void
     */
    public function validateNodeProperty($nodetype = '', $nodepath = '')
    {
        if (empty($nodetype) && empty($nodepath)) {
            $this->results->add(
                    'nodetype',
                    LocalizationUtility::translate(
                            'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:missingNodeProperty',
                            'external_import'
                    ),
                    AbstractMessage::ERROR
            );
        }
    }

    /**
     * Validates the "referenceUid" property.
     *
     * @param string $property Property value
     * @return void
     */
    public function validateReferenceUidProperty($property)
    {
        if (empty($property)) {
            $this->results->add(
                    'referenceUid',
                    LocalizationUtility::translate(
                            'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:missingReferenceUidProperty',
                            'external_import'
                    ),
                    AbstractMessage::ERROR
            );
        }
    }

    /**
     * Validates the "priority" property.
     *
     * @param string $property Property value
     * @return void
     */
    public function validatePriorityProperty($property)
    {
        if (empty($property)) {
            $this->results->add(
                    'priority',
                    LocalizationUtility::translate(
                            'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:defaultPriorityValue',
                            'external_import',
                            array(
                                    Importer::DEFAULT_PRIORITY
                            )
                    ),
                    AbstractMessage::NOTICE
            );
        }
    }

    /**
     * Validates the "pid" property.
     *
     * @param string $property Property value
     * @return void
     */
    public function validatePidProperty($property)
    {
        // TCA property rootLevel defaults to 0
        $rootLevelFlag = $GLOBALS['TCA'][$this->table]['ctrl']['rootLevel'] ?? 0;
        // If the pid is 0, data will be stored on root page.
        if ($property === 0) {
            // Table is allowed on root page, just issue notice to make sure pid was not forgotten
            // NOTE: "rootLevel" is not supposed to be "true", but errors happen and we allow for these.
            if ($rootLevelFlag === -1 || $rootLevelFlag === 1 || $rootLevelFlag) {
                $this->results->add(
                        'pid',
                        LocalizationUtility::translate(
                                'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:pidNotSetStoreRootPage',
                                'external_import'
                        ),
                        AbstractMessage::NOTICE
                );
            } else {
                // Records for current table are not allowed on root page
                $this->results->add(
                        'pid',
                        LocalizationUtility::translate(
                                'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:pidNotSetStoreRootPageNotAllowed',
                                'external_import',
                                array(
                                        $this->table
                                )
                        ),
                        AbstractMessage::ERROR
                );
            }
        } elseif ($property < 0) {
            // Negative pid is invalid
            $this->results->add(
                    'pid',
                    LocalizationUtility::translate(
                            'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:negativePidProperty',
                            'external_import'
                    ),
                    AbstractMessage::ERROR
            );
        } else {
            // Pid is a positive integer, but records for current table can only be stored on root page
            if ($rootLevelFlag === 1) {
                $this->results->add(
                        'pid',
                        LocalizationUtility::translate(
                                'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:invalidPidPropertyOnlyRoot',
                                'external_import',
                                array(
                                        $this->table
                                )
                        ),
                        AbstractMessage::ERROR
                );
            }
        }
    }

    /**
     * Validates the "useColumnIndex" property.
     *
     * @param string $property Property value
     * @param array $columns List of column configurations
     * @return void
     */
    public function validateUseColumnIndexProperty($property, $columns)
    {
        // If useColumnIndex is defined, it needs to match an existing index for the same table
        // If there's no column configuration using that index, issue an error
        if ($property !== null && count($columns) === 0) {
            $this->results->add(
                    'useColumnIndex',
                    LocalizationUtility::translate(
                            'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:wrongUseColumnIndexProperty',
                            'external_import',
                            array(
                                    $property,
                                    $this->table
                            )
                    ),
                    AbstractMessage::ERROR
            );
        }
    }

    /**
     * Validates the "customSteps" property.
     *
     * @param array $property Property value
     * @param array $ctrlConfiguration Full "ctrl" configuration
     * @return void
     */
    public function validateCustomStepsProperty($property, $ctrlConfiguration)
    {
        if ($property !== null && is_array($property) && count($property) > 0) {
            // Define the process default steps, depending on process type
            if (array_key_exists('connector', $ctrlConfiguration)) {
                $steps = Importer::SYNCHRONYZE_DATA_STEPS;
            } else {
                $steps = Importer::IMPORT_DATA_STEPS;
            }
            foreach ($property as $customStepConfiguration) {
                try {
                    $steps = $this->stepUtility->validateCustomStepConfiguration($steps, $customStepConfiguration);
                }
                catch (InvalidCustomStepConfiguration $e) {
                    $this->results->add(
                            'customSteps',
                            LocalizationUtility::translate(
                                    'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:invalidCustomStepsProperty',
                                    'external_import',
                                    array(
                                            $e->getMessage(),
                                            $e->getCode()
                                    )
                            ),
                            AbstractMessage::NOTICE
                    );
                    break;
                }
            }
        }
    }

    /**
     * Returns all validation results.
     *
     * @return ValidationResult
     */
    public function getResults()
    {
        return $this->results;
    }
}