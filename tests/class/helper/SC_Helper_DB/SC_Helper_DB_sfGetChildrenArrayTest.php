<?php

class SC_Helper_DB_sfGetChildrenArrayTest extends SC_Helper_DB_TestBase
{
    /** @var SC_Helper_DB_Ex */
    protected $objDb;
    /** @var int[] */
    protected $category_ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->objDb = new SC_Helper_DB_Ex();
        $this->setUpCategories();
    }

    public function testSfGetChildrenArray()
    {
        $this->actual = $this->objDb->sfGetChildrenArray('dtb_category', 'parent_category_id', 'category_id', $this->category_ids[0]);

        foreach ($this->actual as $category_id) {
            $this->assertTrue(in_array($category_id, $this->category_ids));
        }
    }

    public function testSfGetCatWhere()
    {
        $children_ids = $this->objDb->sfGetChildrenArray('dtb_category', 'parent_category_id', 'category_id', $this->category_ids[0]);
        $where = 'category_id IN ('.SC_Utils_Ex::repeatStrWithSeparator('?', count($children_ids)).')';

        $this->expected = [$where, $children_ids];
        $this->actual = $this->objDb->sfGetCatWhere($this->category_ids[0]);
        $this->verify();
    }

    public function setUpCategories()
    {
        $delete_tables = ['dtb_category', 'dtb_product_categories', 'dtb_category_total_count', 'dtb_category_count'];
        foreach ($delete_tables as $table) {
            $this->objQuery->delete($table);
        }

        $this->category_ids = $this->objGenerator->createCategories();
    }
}
