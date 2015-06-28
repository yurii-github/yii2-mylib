<?php
namespace tests\functional;

use app\models\Books;
class ManageBooksTest extends \tests\AppFunctionalTestCase
{
	// fixture
	public $books; 
	
	protected function setUp()
	{
		$this->books = $this->setupFixture('books');
		parent::setUp();
	}
	
	/*
	function test_BooksFixture()
	{
		$this->assertDataSetsEqual($this->createArrayDataSet(['books' => $this->books['expected']]), $this->getConnection()->createDataSet(['books']));
	}*/
	
	
	
	function test_Site_actionBooks()
	{
		/* @var $controller \app\controllers\SiteController */
		$controller = \Yii::$app->createControllerByID('site');
			
		$json = $controller->actionBooks();
		$object = json_decode($json);
		
		$this->assertInstanceOf('\stdClass', $object);
		$this->assertObjectHasAttribute('page', $object);
		$this->assertEquals(1, $object->page);
		$this->assertObjectHasAttribute('total', $object);
		$this->assertEquals(1, $object->page);
		$this->assertObjectHasAttribute('records', $object);
		$this->assertEquals(3, $object->records);
		$this->assertObjectHasAttribute('rows', $object);
		$this->assertTrue(is_array($object->rows));
		// rows, dummy check
		foreach ($this->books['insert'] as $k => $book) {
			$this->assertInstanceOf('\stdClass', $object->rows[$k]);
			$this->assertEquals($book['book_guid'], $object->rows[$k]->id);
		}
		
		//TODO: filters, page etc
		
/*
 * 				$data = [
					'page' => \Yii::$app->request->get('page'),
					'limit' => \Yii::$app->request->get('rows'),
					'filters' => \Yii::$app->request->get('filters'),
					'sort_column' => \Yii::$app->request->get('sidx'),
					'sort_order'=> \Yii::$app->request->get('sord'),
				];
 */
	
	}
	
	
	
	function test_actionManage_delete()
	{
		/*
		 * ACTION MUST:
		 *
		 * 1. remove record from books table based on book_guid
		 * 
		 * TODO:
		 * 2. remove file if sync is ON
		 */
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['oper'] = 'del';
		$_POST['id'] = 3; // book_guid
		
		/* @var $controller \app\controllers\SiteController */
		$controller = \Yii::$app->createControllerByID('site');
		$controller->actionManage();
		
		unset($this->books['expected'][2]);
		
		$this->assertDataSetsEqual($this->createArrayDataSet(['books' => $this->books['expected']]), $this->getConnection()->createDataSet(['books']));
	}
	
	
	function test_actionManage_add()
	{
		/*
		 * ACTION MUST:
		 * 
		 * 1. generate book guid
		 * 2. generate filename based on title etc..
		 * 3. generate created and updated date
		 *  
		 */
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$book_1 = $this->books['insert'][0];
		$book_1_expected =  $this->books['expected'][0];
		$_POST = $book_1;
		$_POST['oper'] = 'add';
		
		/* @var $controller \app\controllers\SiteController */
		$controller = \Yii::$app->createControllerByID('site');
		$controller->actionManage();
				
		$newRecord = $this->getConnection()->createQueryTable('books', 'SELECT * FROM books WHERE book_guid NOT IN(1,2,3)')->getRow(0); //array
		$oldRecords = $this->getConnection()->createQueryTable('books', 'SELECT * FROM books WHERE book_guid IN(1,2,3)');
		
		// check existing data did not change
		$this->assertTrue($oldRecords->matches($this->createArrayDataSet(['books' => $this->books['expected']])->getTable('books')), 'old records does not match as they were changed');
		
		// pre verify
		$this->assertTrue((bool)preg_match('/[A-Z0-9]{8}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{12}/', $newRecord['book_guid']),
			"book_guid '{$newRecord['book_guid']}' is in wrong format");
		$this->assertEquals((new \DateTime())->format('Y-m-d'), \DateTime::createFromFormat('Y-m-d H:i:s', $newRecord['updated_date'])->format('Y-m-d'));
		$this->assertEquals((new \DateTime())->format('Y-m-d'), \DateTime::createFromFormat('Y-m-d H:i:s', $newRecord['updated_date'])->format('Y-m-d'));
		$this->assertEquals(", ''title book #1'',  [].", $newRecord['filename']);
		
		// mod expected with verified data
		foreach (['book_guid','created_date','updated_date','filename'] as $k) {
			$book_1_expected[$k] = $newRecord[$k];
		}
		
		//verify
		$this->assertArraySubset($book_1_expected, $newRecord);
	}
	
	
	function test_actionManage_edit()
	{
		$this->markTestIncomplete('not finished');
		/*
		 * ACTION MUST:
		 *
		 * 1. not allow changes of book_guid, created and updated date, filename
		 * 2. generate filename based on title etc..
		 * 3. generate updated_date
		 * 4. rename file if sync is ON
		 *
		 */
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$book_1 = $this->books['insert'][0];
		$book_1_expected =  $this->books['expected'][0];
		
		
		$_POST = $book_1;
		$_POST['oper'] = 'edit';
		$_POST['id'] = $book_1['book_guid'];
		
		/* @var $controller \app\controllers\SiteController */
		$controller = \Yii::$app->createControllerByID('site');
		$controller->actionManage();
		
		
	}
	
	
	
}