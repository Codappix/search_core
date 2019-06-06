<?php
namespace Codappix\SearchCore\Domain\Index;

/*
 * Copyright (C) 2017  Daniel Siepmann <coding@daniel-siepmann.de>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301, USA.
 */

use Codappix\SearchCore\Configuration\ConfigurationContainerInterface;
use Codappix\SearchCore\Configuration\InvalidArgumentException;
use Codappix\SearchCore\Connection\ConnectionInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractIndexer implements IndexerInterface
{
    /**
     * @var ConnectionInterface
     */
    protected $connection;

    /**
     * @var ConfigurationContainerInterface
     */
    protected $configuration;

    /**
     * @var string
     */
    protected $identifier = '';

    /**
     * @var \Codappix\SearchCore\DataProcessing\Service
     * @inject
     */
    protected $dataProcessorService;

    /**
     * @var \TYPO3\CMS\Core\Log\Logger
     */
    protected $logger;

    /**
     * Inject log manager to get concrete logger from it.
     *
     * @param \TYPO3\CMS\Core\Log\LogManager $logManager
     */
    public function injectLogger(\TYPO3\CMS\Core\Log\LogManager $logManager)
    {
        $this->logger = $logManager->getLogger(__CLASS__);
    }

    public function setIdentifier(string $identifier)
    {
        $this->identifier = $identifier;
    }

    public function __construct(ConnectionInterface $connection, ConfigurationContainerInterface $configuration)
    {
        $this->connection = $connection;
        $this->configuration = $configuration;
    }

    public function indexAllDocuments()
    {
        $this->logger->info('Start indexing');
        foreach ($this->getRecordGenerator() as $records) {
            if ($records === null) {
                break;
            }

            foreach ($records as &$record) {
                $this->prepareRecord($record);
            }

            $this->logger->debug('Index records.', [$records]);
            $this->connection->addDocuments($this->getDocumentName(), $records);
        }
        $this->logger->info('Finish indexing');
    }

    public function indexDocument(string $identifier)
    {
        $this->logger->info('Start indexing single record.', [$identifier]);
        try {
            $record = $this->getRecord((int) $identifier);
            $this->prepareRecord($record);

            $this->connection->addDocument($this->getDocumentName(), $record);
        } catch (NoRecordFoundException $e) {
            $this->logger->info('Could not index document. Try to delete it therefore.', [$e->getMessage()]);
            $this->connection->deleteDocument($this->getDocumentName(), $identifier);
        }
        $this->logger->info('Finish indexing');
    }

    public function delete()
    {
        $this->logger->info('Start deletion of index.');
        $this->connection->deleteIndex($this->getDocumentName());
        $this->logger->info('Finish deletion.');
    }

    protected function getRecordGenerator() : \Generator
    {
        $offset = 0;
        $limit = $this->getLimit();

        while (($records = $this->getRecords($offset, $limit)) !== null) {
            if ($records !== []) {
                yield $records;
            }
            $offset += $limit;
        }
    }

    protected function prepareRecord(array &$record)
    {
        try {
            foreach ($this->configuration->get('indexing.' . $this->identifier . '.dataProcessing') as $configuration) {
                $record = $this->dataProcessorService->executeDataProcessor($configuration, $record, $this->identifier);
            }
        } catch (InvalidArgumentException $e) {
            // Nothing to do.
        }

        $this->handleAbstract($record);
    }

    protected function handleAbstract(array &$record)
    {
        $record['search_abstract'] = '';

        try {
            $fieldsToUse = GeneralUtility::trimExplode(
                ',',
                $this->configuration->get('indexing.' . $this->identifier . '.abstractFields')
            );
            if ($fieldsToUse === []) {
                return;
            }
            foreach ($fieldsToUse as $fieldToUse) {
                if (isset($record[$fieldToUse]) && trim($record[$fieldToUse])) {
                    $record['search_abstract'] = trim($record[$fieldToUse]);
                    break;
                }
            }
        } catch (InvalidArgumentException $e) {
            return;
        }
    }

    /**
     * Returns the limit to use to fetch records.
     */
    protected function getLimit() : int
    {
        // TODO: Make configurable.
        return 50;
    }

    /**
     * @return array|null
     */
    abstract protected function getRecords(int $offset, int $limit);

    /**
     * @throws NoRecordFoundException If record could not be found.
     */
    abstract protected function getRecord(int $identifier) : array;

    abstract protected function getDocumentName() : string;
}
