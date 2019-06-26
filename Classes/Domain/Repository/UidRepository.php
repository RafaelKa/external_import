<?php
namespace Cobweb\ExternalImport\Domain\Repository;

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
use Cobweb\ExternalImport\Exception\MissingConfigurationException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class used for retrieving UIDs according to external configuration.
 *
 * @package Cobweb\ExternalImport\Domain\Repository
 */
class UidRepository
{
    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var array List of retrieved UIDs
     */
    protected $existingUids;

    /**
     * @var array List of current PIDs
     */
    protected $currentPids;

    /**
     * Sets the Configuration object at run-time.
     *
     * @param Configuration $configuration
     */
    public function setConfiguration(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * Prepares a list of all existing primary keys in the table being synchronized.
     *
     * The result is a hash table of all external primary keys matched to internal primary keys.
     * PIDs are also retrieved. This method is internal, its usage is triggered when using the getter
     * methods.
     *
     * @return void
     * @throws MissingConfigurationException
     */
    protected function retrieveExistingUids()
    {
        // If no configuration was defined, exit early with exception
        if ($this->configuration === null) {
            throw new MissingConfigurationException(
                    'No configuration object defined',
                    1521972733
            );
        }

        $table = $this->configuration->getTable();
        $ctrlConfiguration = $this->configuration->getCtrlConfiguration();
        $where = '1 = 1';
        if ($ctrlConfiguration['enforcePid']) {
            $where = 'pid = ' . (int)$this->configuration->getStoragePid();
        }
        if (!empty($ctrlConfiguration['whereClause'])) {
            $where .= ' AND ' . $ctrlConfiguration['whereClause'];
        }
        $where .= BackendUtility::deleteClause($table);
        $referenceUidField = $ctrlConfiguration['referenceUid'];
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()
                ->removeAll()
                ->add(
                        GeneralUtility::makeInstance(
                                DeletedRestriction::class
                        )
                );
        $res = $queryBuilder->select($referenceUidField, 'uid', 'pid')
                ->from($table)
                ->where($where)
                ->execute();
        if ($res) {
            while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
                // Don't consider records with empty references, as they can't be matched
                // to external data anyway (but a real zero is acceptable)
                if (!empty($row[$referenceUidField]) || $row[$referenceUidField] === '0' || $row[$referenceUidField] === 0) {
                    $this->existingUids[$row[$referenceUidField]] = $row['uid'];
                    $this->currentPids[$row[$referenceUidField]] = $row['pid'];
                }
            }
        }
    }

    /**
     * Returns the list of primary keys of existing records in the database.
     *
     * @return array
     * @throws MissingConfigurationException
     */
    public function getExistingUids()
    {
        // If the list is UIDs is null, assume it wasn't fetched yet and do so
        if ($this->existingUids === null) {
            $this->retrieveExistingUids();
        }
        return $this->existingUids;
    }

    /**
     * Resets the list of primary keys.
     *
     * @return void
     */
    public function resetExistingUids()
    {
        $this->existingUids = null;
    }

    /**
     * Returns the list of storage PIDs of existing records in the database.
     *
     * @return array
     * @throws MissingConfigurationException
     */
    public function getCurrentPids()
    {
        // If the list is UIDs is null, assume it wasn't fetched yet and do so
        if ($this->currentPids === null) {
            $this->retrieveExistingUids();
        }
        return $this->currentPids;
    }

    /**
     * Resets the list of storage PIDs.
     *
     * @return void
     */
    public function resetCurrentPids()
    {
        $this->currentPids = null;
    }
}