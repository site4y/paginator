<?php

namespace s4y;

/**
 * Используется для разделения получаемых данных с БД постранично
 *
 * @property int countPerPage Количество записей на страницу, если 0 - постраничное разделение не используется
 * @property int count Общее количество записей
 * @property int pageCount Количество страниц
 * @property int page Текущая страница
 * @property int first Номер первой записи (начиная с 1)
 * @property int last Номер последней записи на тек.странице
 */
class Paginator
{
    /** @var \Zend_Db_Adapter_Abstract|array */
    protected $db;

    /** @var  string */
    protected $sql;

    protected $_countPerPage;

    protected $_count = false;

    protected $_pageCount = false;

    protected $_page = 0;

    /**
     * @param $select \Zend_Db_Select
     */
    public function __construct($db, $sql, $countPerPage = 20, $page = 1) {
        $this->db = $db;
        $this->sql = $sql;
        $this->_countPerPage = $countPerPage;
    }

    static function fromArray($data, $countPerPage = 20, $page = 1)
    {
        return new self($data, '', $countPerPage, $page);
    }

    /**
     * @param $select \Zend_Db_Select
     * @param int $countPerPage
     * @param int $page
     * @return Paginator
     */
    static function fromSelect($select, $countPerPage = 20, $page = 1)
    {
        return new self($select->getAdapter(), $select->assemble(), $countPerPage, $page);
    }

    public function getCount()
    {
        if ($this->_count === false) {
            if (is_array($this->db)) {
                $this->_count = count($this->data);
            } else {
                $this->_count = (int)$this->db->fetchOne(
                    $this->db->select()->from([
                    's4y_paginator_select' =>
                        new \Zend_Db_Expr('('.$this->sql.')')
                ], ['rowcount' => 'COUNT(*)']));
            }
            $this->_pageCount = false;
        }
        return $this->_count;
    }

    public function getPageCount() {
        if ($this->_pageCount === false) {
            if ($this->_countPerPage > 0) {
                $this->_pageCount = ceil($this->getCount() / $this->_countPerPage);
            } else {
                $this->_pageCount = (($this->getCount() > 0) ? 1 : 0);
            }
        }
        return $this->_pageCount;
    }

    public function getPage() {
        $p = $this->_page;
        if ($p <= 0) {
            $p = 1;
        }
        if ($p > $this->getPageCount()) {
            $p = $this->getPageCount();
        }
        return $p;
    }

    public function setCountPerPage($countPerPage) {
        $this->_countPerPage = $countPerPage;
        $this->_pageCount = false;
    }

    public function setPage($page) {
        $this->_page = (int) $page;
        $this->_pageCount = false;
        return $this->getPage();
    }

    public function getFirstNumber() {
        $page = $this->getPage();
        if ($page) {
            return ($page - 1) * $this->_countPerPage + 1;
        } else return 0;
    }

    public function getLastNumber() {
        $page = $this->getPage();
        if ($page) {
            if ($this->_countPerPage > 0) {
                return min($this->getCount(), ($page) * $this->_countPerPage);
            } else {
                return ($this->count);
            }
        } else return 0;
    }

    public function __get($name)
    {
        switch ($name) {
            case 'count': return $this->getCount();
            case 'countPerPage': return $this->_countPerPage;
            case 'page': return $this->getPage();
            case 'pageCount': return $this->getPageCount();
            case 'first': return $this->getFirstNumber();
            case 'last': return $this->getLastNumber();
        }
    }

    public function __set($name, $value)
    {
        switch ($name) {
            case 'countPerPage':
                $this->setCountPerPage($value);
                break;
            case 'page':
                $this->setPage($value);
                break;
        }
    }

    protected function sqlAddPageParams($sql)
    {
        $p = $this->getPage();
        if ($this->_countPerPage > 0 && $p > 0) {
            return $sql . ' LIMIT ' . (($p-1) * $this->_countPerPage) . ',' . $this->_countPerPage;
        }
        return $sql;
    }

    public function fetchPage($page = null)
    {
        if (isset($page)) {
            $this->setPage($page);
        }
        if (is_array($this->db)) {
            if ($this->_countPerPage == 0) return $this->db;
            $p = $this->getPage();
            $count = $this->getCount();
            $start = (($p-1) * $this->_countPerPage);
            $end = min($start + $this->_countPerPage, $count - 1);
            if ($start == 0 && $end == $count - 1) return $this->db;
            return array_slice($this->db, $start, $this->_countPerPage);
        } else {
            $sql = $this->sqlAddPageParams($this->sql);
            return $this->db->fetchAll($sql);
        }
    }

    public function getRowPosition($where, $bind = null) {
        //TODO: search in array
        if (is_array($this->_db)) return null;

        $select = $this->_db->select()->from(['s4y_paginator' =>
            new \Zend_Db_Expr('(SELECT *, @rownum := @rownum + 1 AS s4y_paginator_pos FROM ('
                .$this->sql.
                ') s4y_paginator_t JOIN (SELECT @rownum := 0) s4y_paginator_c)')
        ], ['s4y_paginator_pos']);

        if (is_array($where)) {
            foreach ($where as $w => $b) {
                $select->where($w, $b);
            }
        } else {
            $select->where($where, $bind);
        }

        return $this->_db->fetchOne($select);
    }

    public function getRowPage($where, $bind = null) {
        $pos = $this->getRowPosition($where, $bind);
        if (!$pos) return false;
        if ($this->_countPerPage == 0) {
            return 1;
        } else {
            return ceil($pos / $this->_countPerPage);
        }
    }

    public function seekTo($where, $bind = null) {
        $page = $this->getRowPage($where, $bind);
        if ($page) $this->setPage($page);
        return $page;
    }


}