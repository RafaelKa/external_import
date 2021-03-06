<?php

namespace Cobweb\ExternalImport\Tests\Unit\Validator;

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

use Cobweb\ExternalImport\Domain\Model\Configuration;
use Cobweb\ExternalImport\Validator\ControlConfigurationValidator;
use Nimut\TestingFramework\TestCase\FunctionalTestCase;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

class ControlConfigurationValidatorTest extends FunctionalTestCase
{

    /**
     * @var ControlConfigurationValidator
     */
    protected $subject;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    public function setUp()
    {
        parent::setUp();
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $this->subject = $this->objectManager->get(ControlConfigurationValidator::class);
    }

    public function validConfigurationProvider(): array
    {
        return array(
                'Typical configuration for array type' => array(
                        array(
                                'data' => 'array',
                                'referenceUid' => 'external_id',
                                'pid' => 12
                        )
                ),
                'Typical configuration for xml type (nodetype)' => array(
                        array(
                                'data' => 'xml',
                                'nodetype' => 'foo',
                                'referenceUid' => 'external_id',
                                'pid' => 12
                        )
                ),
                'Typical configuration for xml type (nodepath)' => array(
                        array(
                                'data' => 'xml',
                                'nodepath' => '//foo',
                                'referenceUid' => 'external_id',
                                'pid' => 12
                        )
                )
        );
    }

    /**
     * @param array $configuration
     * @test
     * @dataProvider validConfigurationProvider
     */
    public function isValidReturnsTrueForValidConfiguration($configuration)
    {
        self::assertTrue(
                $this->subject->isValid(
                        $this->prepareConfigurationObject(
                                'tt_content',
                                $configuration
                        )
                )
        );
    }

    public function invalidConfigurationProvider()
    {
        return array(
                'Missing data property' => array(
                        array(
                                'reference_uid' => 'external_id'
                        )
                ),
                'Invalid data property' => array(
                        array(
                                'data' => 'foo',
                                'reference_uid' => 'external_id'
                        )
                ),
                'Invalid connector property' => array(
                        array(
                                'data' => 'array',
                                'reference_uid' => 'external_id',
                                'connector' => time()
                        )
                ),
                'Missing reference_uid property' => array(
                        array(
                                'data' => 'array'
                        )
                )
        );
    }

    /**
     * @param array $configuration
     * @test
     * @dataProvider invalidConfigurationProvider
     */
    public function isValidReturnsFalseForInvalidConfiguration($configuration)
    {
        self::assertFalse(
                $this->subject->isValid(
                        $this->prepareConfigurationObject(
                                'tt_content',
                                $configuration
                        )
                )
        );
    }

    public function invalidDataPropertyConfigurationProvider()
    {
        return array(
                'Missing data property' => array(
                        array()
                ),
                'Invalid data property' => array(
                        array(
                                'data' => 'foo'
                        )
                )
        );
    }

    /**
     * @param array $configuration
     * @test
     * @dataProvider invalidDataPropertyConfigurationProvider
     */
    public function validateDataPropertyWithInvalidValueRaisesError($configuration)
    {
        $this->subject->isValid(
                $this->prepareConfigurationObject(
                        'tt_content',
                        $configuration
                )
        );
        $result = $this->subject->getResults()->getForProperty('data');
        self::assertSame(
                FlashMessage::ERROR,
                $result['severity']
        );
    }

    /**
     * @test
     */
    public function validateConnectorPropertyWithInvalidValueRaisesError()
    {
        $this->subject->isValid(
                $this->prepareConfigurationObject(
                        'tt_content',
                        [
                            // Some random connector name
                            'connector' => time()
                        ]
                )
        );
        $result = $this->subject->getResults()->getForProperty('connector');
        self::assertSame(
                FlashMessage::ERROR,
                $result['severity']
        );
    }

    public function invalidDataHandlerPropertyConfigurationProvider()
    {
        return array(
                'Not existing class' => array(
                        array(
                                'dataHandler' => 'Cobweb\\ExternalImport\\' . time()
                        )
                ),
                'Class not implementing proper interface' => array(
                        array(
                                'dataHandler' => \Cobweb\ExternalImport\Importer::class
                        )
                )
        );
    }

    /**
     * @param array $configuration
     * @test
     * @dataProvider invalidDataHandlerPropertyConfigurationProvider
     */
    public function validateDataHandlerPropertyWithInvalidValueRaisesNotice($configuration)
    {
        $this->subject->isValid(
                $this->prepareConfigurationObject(
                        'tt_content',
                        $configuration
                )
        );
        $result = $this->subject->getResults()->getForProperty('dataHandler');
        self::assertSame(
                FlashMessage::NOTICE,
                $result['severity']
        );
    }

    /**
     * @test
     */
    public function validateNodetypePropertyForXmlDataWithEmptyValueRaisesError()
    {
        $this->subject->isValid(
                $this->prepareConfigurationObject(
                        'tt_content',
                        [
                                'data' => 'xml'
                        ]
                )
        );
        $result = $this->subject->getResults()->getForProperty('nodetype');
        self::assertSame(
                FlashMessage::ERROR,
                $result['severity']
        );
    }

    /**
     * @test
     */
    public function validateReferenceUidPropertyWithEmptyValueRaisesError()
    {
        $this->subject->isValid(
                $this->prepareConfigurationObject(
                        'tt_content',
                        []
                )
        );
        $result = $this->subject->getResults()->getForProperty('referenceUid');
        self::assertSame(
                FlashMessage::ERROR,
                $result['severity']
        );
    }

    /**
     * @test
     */
    public function validatePriorityPropertyWithEmptyValueRaisesNotice()
    {
        $this->subject->isValid(
                $this->prepareConfigurationObject(
                        'tt_content',
                        [
                                'connector' => 'foo'
                        ]
                )
        );
        $result = $this->subject->getResults()->getForProperty('priority');
        self::assertSame(
                FlashMessage::NOTICE,
                $result['severity']
        );
    }

    /**
     * @test
     */
    public function validatePidPropertyWithEmptyValueForRootTableRaisesNotice()
    {
        $this->subject->isValid(
                $this->prepareConfigurationObject(
                        'be_users',
                        [
                            // NOTE: normally, configuration is parsed by the ConfigurationRepository and pid would
                            // be set to 0 if missing from configuration
                            'pid' => 0
                        ]
                )
        );
        $result = $this->subject->getResults()->getForProperty('pid');
        self::assertSame(
                FlashMessage::NOTICE,
                $result['severity']
        );
    }

    public function invalidPidPropertyConfigurationProvider()
    {
        return array(
                'Missing pid, non-root table' => array(
                        'tt_content',
                        array(
                            // NOTE: normally, configuration is parsed by the ConfigurationRepository and pid would
                            // be set to 0 if missing from configuration
                            'pid' => 0
                        )
                ),
                'Negative pid' => array(
                        'tt_content',
                        array(
                                'pid' => -12
                        )
                ),
                'Positive pid, root table' => array(
                        'be_users',
                        array(
                                'pid' => 12
                        )
                )
        );
    }

    /**
     * @param string $table Table name
     * @param array $configuration Configuration
     * @test
     * @dataProvider invalidPidPropertyConfigurationProvider
     */
    public function validatePidPropertyWithInvalidValueRaisesError($table, $configuration)
    {
        $this->subject->isValid(
                $this->prepareConfigurationObject(
                        $table,
                        $configuration
                )
        );
        $result = $this->subject->getResults()->getForProperty('pid');
        self::assertSame(
                FlashMessage::ERROR,
                $result['severity']
        );
    }

    /**
     * @test
     */
    public function validateUseColumnIndexPropertyWithInvalidValueRaisesError()
    {
        $this->subject->isValid(
                $this->prepareConfigurationObject(
                        'tt_content',
                        [
                                'useColumnIndex' => 'foo'
                        ]
                )
        );
        $result = $this->subject->getResults()->getForProperty('useColumnIndex');
        self::assertSame(
                FlashMessage::ERROR,
                $result['severity']
        );
    }

    /**
     * @test
     */
    public function addResultAddsResults()
    {
        $results = array(
                'foo' => array(
                        'severity' => FlashMessage::WARNING,
                        'message' => 'Something went wrong'
                )
        );
        $this->subject->getResults()->add(
                'foo',
                $results['foo']['message']
        );
        self::assertSame(
                $results,
                $this->subject->getResults()->getAll()
        );
    }

    /**
     * @test
     */
    public function addResultForPropertyAddsResultsForProperty()
    {
        $results = array(
                'foo' => array(
                        'severity' => FlashMessage::WARNING,
                        'message' => 'Something went wrong'
                )
        );
        $this->subject->getResults()->add(
                'foo',
                $results['foo']['message']
        );
        $resultForProperty = $this->subject->getResults()->getForProperty('foo');
        self::assertSame(
                $results['foo'],
                $resultForProperty
        );
    }

    /**
     * @test
     */
    public function addResultForSeverityAddsResultsForSeverity()
    {
        $results = [
                'foo' => [
                        'severity' => FlashMessage::WARNING,
                        'message' => 'Something went wrong'
                ]
        ];
        $this->subject->getResults()->add(
                'foo',
                $results['foo']['message']
        );
        $resultForProperty = $this->subject->getResults()->getForSeverity(FlashMessage::WARNING);
        self::assertSame(
                [
                        'foo' => $results['foo']['message']
                ],
                $resultForProperty
        );
    }

    /**
     * Prepares a configuration object with the usual parameters used in this test suite.
     *
     * @param string $table
     * @param array $configuration
     * @return Configuration
     */
    protected function prepareConfigurationObject($table, $configuration)
    {
        $configurationObject = $this->objectManager->get(Configuration::class);
        $configurationObject->setTable($table);
        $configurationObject->setCtrlConfiguration($configuration);
        return $configurationObject;
    }
}