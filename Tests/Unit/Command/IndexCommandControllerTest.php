<?php

namespace Codappix\SearchCore\Tests\Unit\Command;

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

use Codappix\SearchCore\Command\IndexCommandController;
use Codappix\SearchCore\Domain\Index\IndexerFactory;
use Codappix\SearchCore\Domain\Index\NoMatchingIndexerException;
use Codappix\SearchCore\Domain\Index\TcaIndexer;
use Codappix\SearchCore\Tests\Unit\AbstractUnitTestCase;

class IndexCommandControllerTest extends AbstractUnitTestCase
{
    /**
     * @var IndexCommandController
     */
    protected $subject;

    /**
     * @var IndexerFactory
     */
    protected $indexerFactory;

    public function setUp()
    {
        parent::setUp();

        $this->indexerFactory = $this->getMockBuilder(IndexerFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->subject = $this->getMockBuilder(IndexCommandController::class)
            ->disableOriginalConstructor()
            ->setMethods(['quit', 'outputLine'])
            ->getMock();
        $this->subject->injectIndexerFactory($this->indexerFactory);
    }

    /**
     * @test
     */
    public function indexerStopsForNonAllowedTable()
    {
        $this->subject->expects($this->once())
            ->method('outputLine')
            ->with('No indexer found for: nonAllowedTable.');
        $this->indexerFactory->expects($this->once())
            ->method('getIndexer')
            ->with('nonAllowedTable')
            ->will($this->throwException(new NoMatchingIndexerException));

        $this->subject->indexCommand('nonAllowedTable');
    }

    /**
     * @test
     */
    public function indexerExecutesForAllowedTable()
    {
        $indexerMock = $this->getMockBuilder(TcaIndexer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $indexerMock->expects($this->any())
            ->method('getIdentifier')
            ->willReturn('allowedTable');
        $this->subject->expects($this->never())
            ->method('quit');
        $this->subject->expects($this->once())
            ->method('outputLine')
            ->with('Documents in index allowedTable were indexed.');
        $this->indexerFactory->expects($this->once())
            ->method('getIndexer')
            ->with('allowedTable')
            ->will($this->returnValue($indexerMock));

        $this->subject->indexCommand('allowedTable');
    }

    /**
     * @test
     */
    public function deletionOfDocumentsIsPossible()
    {
        $indexerMock = $this->getMockBuilder(TcaIndexer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $indexerMock->expects($this->any())
            ->method('getIdentifier')
            ->willReturn('allowedTable');
        $this->subject->expects($this->once())
            ->method('outputLine')
            ->with('Documents in index allowedTable were deleted.');
        $this->indexerFactory->expects($this->once())
            ->method('getIndexer')
            ->with('allowedTable')
            ->will($this->returnValue($indexerMock));

        $indexerMock->expects($this->once())
            ->method('deleteAllDocuments');
        $this->subject->deleteDocumentsCommand('allowedTable');
    }

    /**
     * @test
     */
    public function deletionOfIndexIsPossible()
    {
        $indexerMock = $this->getMockBuilder(TcaIndexer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $indexerMock->expects($this->any())
            ->method('getIdentifier')
            ->willReturn('pages');
        $this->subject->expects($this->once())
            ->method('outputLine')
            ->with('Index pages was deleted.');
        $this->indexerFactory->expects($this->once())
            ->method('getIndexer')
            ->with('pages')
            ->will($this->returnValue($indexerMock));

        $indexerMock->expects($this->once())
            ->method('delete');
        $this->subject->deleteCommand('pages');
    }

    /**
     * @test
     */
    public function deletionForNonExistingIndexerDoesNotWork()
    {
        $this->subject->expects($this->once())
            ->method('outputLine')
            ->with('No indexer found for: nonAllowedTable.');
        $this->indexerFactory->expects($this->once())
            ->method('getIndexer')
            ->with('nonAllowedTable')
            ->will($this->throwException(new NoMatchingIndexerException));

        $this->subject->deleteCommand('nonAllowedTable');
    }

    // As all methods use the same code base, we test the logic for multiple
    // identifiers only for indexing.

    /**
     * @test
     */
    public function indexerExecutesForAllowedTables()
    {
        $indexerMock = $this->getMockBuilder(TcaIndexer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $indexerMock->expects($this->any())
            ->method('getIdentifier')
            ->will($this->onConsecutiveCalls('allowedTable', 'anotherTable'));
        $this->subject->expects($this->never())
            ->method('quit');
        $this->subject->expects($this->exactly(2))
            ->method('outputLine')
            ->withConsecutive(
                ['Documents in index allowedTable were indexed.'],
                ['Documents in index anotherTable were indexed.']
            );
        $this->indexerFactory->expects($this->exactly(2))
            ->method('getIndexer')
            ->withConsecutive(['allowedTable'], ['anotherTable'])
            ->will($this->returnValue($indexerMock));

        $this->subject->indexCommand('allowedTable, anotherTable');
    }

    /**
     * @test
     */
    public function indexerSkipsEmptyIdentifier()
    {
        $indexerMock = $this->getMockBuilder(TcaIndexer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $indexerMock->expects($this->any())
            ->method('getIdentifier')
            ->willReturn('allowedTable');
        $this->subject->expects($this->never())
            ->method('quit');
        $this->subject->expects($this->once())
            ->method('outputLine')
            ->with('Documents in index allowedTable were indexed.');
        $this->indexerFactory->expects($this->once())
            ->method('getIndexer')
            ->with('allowedTable')
            ->will($this->returnValue($indexerMock));

        $this->subject->indexCommand('allowedTable, ');
    }

    /**
     * @test
     */
    public function indexerSkipsAndOutputsNonExistingIdentifier()
    {
        $indexerMock = $this->getMockBuilder(TcaIndexer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $indexerMock->expects($this->any())
            ->method('getIdentifier')
            ->willReturn('allowedTable');
        $this->subject->expects($this->never())
            ->method('quit');
        $this->subject->expects($this->exactly(2))
            ->method('outputLine')
            ->withConsecutive(
                ['No indexer found for: nonExisting.'],
                ['Documents in index allowedTable were indexed.']
            );
        $this->indexerFactory->expects($this->exactly(2))
            ->method('getIndexer')
            ->withConsecutive(['nonExisting'], ['allowedTable'])
            ->will($this->onConsecutiveCalls(
                $this->throwException(new NoMatchingIndexerException),
                $this->returnValue($indexerMock)
            ));

        $this->subject->indexCommand('nonExisting, allowedTable');
    }
}
