<?php
namespace Topxia\Service\Article\Dao\Impl;

use Topxia\Service\Common\BaseDao;
use Topxia\Service\Article\Dao\ArticleDao;

class ArticleDaoImpl extends BaseDao implements ArticleDao
{
	protected $table = 'article';

	private $serializeFields = array(
            'tagIds' => 'json',
    );

	public function getArticle($id)
	{
        $sql = "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($id)) ? : null;
	}

	public function getArticlePrevious($createdTime)
	{
		$sql = "SELECT * FROM {$this->table} WHERE createdTime < ? ORDER by `createdTime` DESC LIMIT 1";
		return $this->getConnection()->fetchAssoc($sql,array($createdTime) ? :null);
	}
	
	public function getArticleNext($createdTime)
	{
		$sql = "SELECT * FROM {$this->table} WHERE createdTime > ? ORDER by `createdTime` ASC LIMIT 1";
		return $this->getConnection()->fetchAssoc($sql,array($createdTime) ? :null);
	}

	public function getArticleByAlias($alias)
	{
        $sql = "SELECT * FROM {$this->table} WHERE alias = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($alias)) ? : null;
	}

	public function findArticlesByCategoryIds(array $categoryIds, $start, $limit)
	{
		$this->filterStartLimit($start, $limit);
		if(empty($categoryIds)){ return array(); };
        $marks = str_repeat('?,', count($categoryIds) - 1) . '?';
        $sql = "SELECT * FROM {$this->table} WHERE categoryId in ({$marks}) ORDER BY createdTime DESC LIMIT {$start}, {$limit}";

        return $this->getConnection()->fetchAll($sql, $categoryIds);
	}

	public function findArticlesCount(array $categoryIds)
	{
		if(empty($categoryIds)){ return array(); };
        $marks = str_repeat('?,', count($categoryIds) - 1) . '?';
		$sql = "SELECT COUNT(id) FROM {$this->table} WHERE categoryId in ({$marks})";

        return $this->getConnection()->fetchColumn($sql, $categoryIds);
	}

	public function searchArticles($conditions, $orderBys, $start, $limit)
	{
		$this->filterStartLimit($start, $limit);

		$builder = $this->_createSearchQueryBuilder($conditions)
			->select('*')
			->setFirstResult($start)
			->setMaxResults($limit);
		foreach ($orderBys as $orderBy) {
			$builder->addOrderBy($orderBy[0], $orderBy[1]);
		}

		return $builder->execute()->fetchAll() ? : array();
	}

	public function searchArticlesCount($conditions)
	{	
        $builder = $this->_createSearchQueryBuilder($conditions)
            ->select('COUNT(id)');
        return $builder->execute()->fetchColumn(0);
	}

	public function addArticle($article)
	{
        $affected = $this->getConnection()->insert($this->table, $article);
        if ($affected <= 0) {
            throw $this->createDaoException('Insert Article error.');
        }
        return $this->getArticle($this->getConnection()->lastInsertId());
	}

	public function waveArticle($id,$field,$diff)
	{
		$fields = array('hits');
		if (!in_array($field, $fields)) {
			throw \InvalidArgumentException(sprintf("%s字段不允许增减，只有%s才被允许增减", $field, implode(',', $fields)));
		}
		$sql = "UPDATE {$this->table} SET {$field} = {$field} + ? WHERE id = ? LIMIT 1";
        return $this->getConnection()->executeQuery($sql, array($diff, $id));
	}

	public function updateArticle($id, $Article)
	{
        $this->getConnection()->update($this->table, $Article, array('id' => $id));
        return $this->getArticle($id);
	}

	public function deleteArticle($id)
	{
		return $this->getConnection()->delete($this->table, array('id' => $id));
	}	

	private function _createSearchQueryBuilder($conditions)
	{
		$conditions = array_filter($conditions);
		
		if(array_key_exists('property',$conditions)){
			$key = $conditions['property'];
			$conditions[$key] = 1;
		}

		if(array_key_exists('hasPicture',$conditions)){
			if ($conditions['hasPicture'] == true) {
				$conditions['pictureNull'] = "";		
				unset($conditions['hasPicture']);
			}
		}

		if(isset($conditions['keywords'])){
			$conditions['keywords'] = "%{$conditions['keywords']}%";
		}

		$builder = $this->createDynamicQueryBuilder($conditions)
			->from($this->table, 'article')
			->andWhere('status = :status')
			->andWhere('categoryId = :categoryId')
			->andWhere('featured = :featured')
			->andWhere('promoted = :promoted')
			->andWhere('sticky = :sticky')
			->andWhere('title LIKE :keywords')
			->andWhere('picture != :pictureNull')
			->andWhere('categoryId = :categoryId')

            // @todo 
			->andWhere('id = :id')
			->andWhere('id > :idMoreThan')
			->andWhere('id < :idLessThan');

        if (isset($conditions['categoryIds'])) {
            $categoryIds = array();
            foreach ($conditions['categoryIds'] as $categoryId) {
                if (ctype_digit((string)abs($categoryId))) {
                    $categoryIds[] = $categoryId;
                }
            }
            if ($categoryIds) {
                $categoryIds = join(',', $categoryIds);
                $builder->andStaticWhere("categoryId IN ($categoryIds)");
            }
        }

		return $builder;
	}
}