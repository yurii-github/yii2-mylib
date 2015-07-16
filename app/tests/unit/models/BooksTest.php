<?php
namespace tests\models;

use app\models\Books;
use app\components\Configuration;

class BooksTest extends \tests\AppTestCase
{
	protected $books;

	protected function setUp()
	{
		$this->mockYiiApplication();
		
		$this->books = $this->setupFixture('books');
		parent::setUp();
	}
	
	
	public function pSync()
	{
		// sync options:  ON and OFF
		return [ [true], [false] ];
	}

	
	/**
	 * @dataProvider pSync
	 * @param bool $sync
	 */
	public function test_Update($sync)
	{		
		$book = Books::findOne(['book_guid' => 1]);
		$book_filename = \Yii::$app->mycfg->library->directory.$book->filename;
		file_put_contents($book_filename, 'something');
		
		$book->title = 'xxx';
		\Yii::$app->mycfg->library->sync = $sync;
		$book->save();

		$new_filename = \Yii::$app->mycfg->library->directory.$book->filename;
		
		// rename check
		if ($sync) { //ON
			$this->assertTrue(file_exists($new_filename), 'SYNC ON: no new file. rename failed');
			$this->assertFalse(file_exists($book_filename), 'SYNC ON: old file not removed. rename failed');
			$this->assertEquals('something', file_get_contents($new_filename));
		} else { //OFF
			$this->assertFalse(file_exists($new_filename), 'SYNC OFF: new file created. file renamed. must not occur');
			$this->assertTrue(file_exists($book_filename), 'SYNC OFF: old file removed. file renamed. must not occur.');
			$this->assertEquals('something', file_get_contents($book_filename));
		}

		//TODO: db check
	}
	

	/**
	 * @expectedException yii\base\InvalidValueException
	 * @expectedExceptionCode 1
	 * @expectedExceptionMessage Sync for file failed. Source file 'vfs://base/data/books/filename-1' does not exist
	 */
	function test_Update_NoFile_SyncON()
	{
		/* @var $book Books */
		\Yii::$app->mycfg->library->sync = true;
		$book = Books::findOne(['book_guid' => 1]);
		$book->save();
	}
		
	
	/**
	 * @dataProvider pSync
	 * @param bool $sync
	 * @param bool $book_exists
	 */
	public function test_Delete($sync)
	{
		$book_delete = $this->books['expected'][0];
		unset($this->books['expected'][0]); //remove deleted from expected
		$this->books['expected'] = array_values($this->books['expected']);
		
		//var_dump($this->books['expected']); die;
		//prepare
		$book = Books::findOne(['book_guid' => $book_delete['book_guid']]);
		$book_delete_filename = \Yii::getAlias('@app/data/books/').$book_delete['filename'];
		file_put_contents($book_delete_filename, 'something');
		\Yii::$app->mycfg->library->sync = $sync;
		//act
		$book->delete();
		
		//verify
		if ($sync) {
			$this->assertFalse(file_exists($book_delete_filename), "Sync ON. book '{$book_delete_filename}' was not deleted");
		} else {
			$this->assertTrue(file_exists($book_delete_filename), "Sync OFF. book '{$book_delete_filename}' was deleted");
		}
		
		$this->assertDataSetsEqual(
			$this->createArrayDataSet(['books' => $this->books['expected']]),
			$this->getConnection()->createDataSet(['books']));
	}
	
	
	public function test_Delete_Warning_FileWasDeletedWithSyncON()
	{
		$log_filename = $this->initAppFileSystem() . '/runtime/logs/logs.txt';
		
		$this->mockYiiApplication( [
			'bootstrap' => [ 'log'	],
			'components' => [
				'log' => [
					'traceLevel' => 0,
					'targets' => [
						'generic'=> [
							'class' => \yii\log\FileTarget::class,
							'logVars' => [],
							'logFile' => $log_filename,
							'enabled' => true,
							'levels' => ['warning'],
						]
					]
				]
			]
		]);
		
		$book_delete = $this->books['expected'][0];
		\Yii::$app->mycfg->library->sync = true;
		
		$book = Books::findOne(['book_guid' => $book_delete['book_guid']]);
		$book->delete();
		\Yii::getLogger()->flush(true);
		
		$this->assertRegExp('/filename\-1\' was removed before record deletion with sync enabled$/', file_get_contents($log_filename));
	}
	
	
	
	
	function test_jgridBooks()
	{	
		//OK
		$get = ['page' => 1,'limit' => 10, 'sort_column' => 'created_date','sort_order'=> 'desc', 'filters' => '' ];
		$resp = Books::jgridBooks($get);
		$this->assertInstanceOf('\stdClass', $resp);
		$this->assertEquals($resp->page, 1);
		$this->assertEquals($resp->records, count($this->dataset['books']));
		$this->assertEquals(count($resp->rows), $resp->records);
		$book1 = $resp->rows[0];
		$this->assertEquals(true, is_array($book1));
		$this->assertEquals($this->dataset['books'][0]['book_guid'], $book1['id']);
		$this->assertEquals( (new \DateTime($this->dataset['books'][0]['created_date']))->format('d-m-Y'), $book1['cell'][0]);
		//TODO: more stuff?
		
		// empty get, test defaults
		unset($get['page']);
		$resp = Books::jgridBooks([]);
		$this->assertInstanceOf('\stdClass', $resp);
		$this->assertEquals($resp->page, 1);
		$this->assertEquals($resp->records, count($this->dataset['books']));
	}
	
	
}


