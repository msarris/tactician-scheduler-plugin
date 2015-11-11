<?php
namespace ConnectHolland\Tactician\SchedulerPlugin\Scheduler\Tests;

use ConnectHolland\Tactician\SchedulerPlugin\Command\ScheduledCommandInterface;
use ConnectHolland\Tactician\SchedulerPlugin\Scheduler\MongoScheduler;
use ConnectHolland\Tactician\SchedulerPlugin\Tests\Fixtures\Command\ScheduledCommand;
use ConnectHolland\Tactician\SchedulerPlugin\Tests\Fixtures\Command\StatefulCommand;
use MongoClient;
use MongoId;
use PHPUnit_Framework_TestCase;

/**
 * Unit test to test scheduling commands using a mongo database
 *
 * @author Ron Rademaker
 */
class MongoSchedulerTest extends PHPUnit_Framework_TestCase
{
    /**
     * Drop any leftover test commands
     */
    public function tearDown()
    {
        $con = new MongoClient('mongodb://localhost');
        $db = $con->selectDB('ConnectHollandTacticianSchedulerTest');
        $db->dropCollection('MongoScheduler');
    }

    /**
     * testScheduleCommand
     *
     * @dataProvider provideTestCommands
     */
    public function testScheduleCommand(ScheduledCommandInterface $command)
    {
        $con = new MongoClient('mongodb://localhost');
        $db = $con->selectDB('ConnectHollandTacticianSchedulerTest');
        $collection = $db->selectCollection('MongoScheduler');
        $scheduler = new MongoScheduler($collection);

        $command->setTimestamp(time() + 1);
        $identifier = $scheduler->schedule($command);

        $stored = $collection->findOne(['_id' => new MongoId($identifier)]);

        $this->assertEquals($command, unserialize($stored['command']));
        $collection->remove(['_id' => new MongoId($identifier)]);
    }

    /**
     * testGetCommands
     */
    public function testGetCommands()
    {
        $con = new MongoClient('mongodb://localhost');
        $db = $con->selectDB('ConnectHollandTacticianSchedulerTest');
        $collection = $db->selectCollection('MongoScheduler');
        $scheduler = new MongoScheduler($collection);

        $command = new ScheduledCommand();
        $command->setTimestamp(time() + 1);
        $identifier = $scheduler->schedule($command);
        $nothing = $scheduler->getCommands();
        $this->assertEquals(0, count($nothing));
        sleep(1);

        $todo = $scheduler->getCommands();
        $this->assertEquals(1, count($todo));
        $this->assertEquals($command, $todo[0]);

        $stored = $collection->findOne(['_id' => new MongoId($identifier)]);

        $this->assertEmpty($stored);
    }

    /**
     * Tests that stateful commands are kept
     */
    public function testStatefulCommandsAreKept()
    {
        $con = new MongoClient('mongodb://localhost');
        $db = $con->selectDB('ConnectHollandTacticianSchedulerTest');
        $collection = $db->selectCollection('MongoScheduler');
        $scheduler = new MongoScheduler($collection);

        $command = new StatefulCommand();
        $command->setTimestamp(time() + 1);
        $identifier = $scheduler->schedule($command);
        $nothing = $scheduler->getCommands();
        $this->assertEquals(0, count($nothing));
        sleep(1);

        $todo = $scheduler->getCommands();
        $this->assertEquals(1, count($todo));
        $this->assertEquals('executing', $todo[0]->getFiniteState());

        $stored = $collection->findOne(['_id' => new MongoId($identifier)]);

        $this->assertNotEmpty($stored);

        $dontExecuteTwice = $scheduler->getCommands();
        $this->assertEquals(0, count($dontExecuteTwice));
    }

    /**
     * Provide test commands
     *
     * @return array
     */
    public function provideTestCommands()
    {
        return [
            [new ScheduledCommand()],
            [new StatefulCommand()]
        ];
    }
}
