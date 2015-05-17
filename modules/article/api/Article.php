<?php
namespace yii\easyii\modules\article\api;

use Yii;

use yii\data\ActiveDataProvider;
use yii\easyii\modules\article\models\Category;
use yii\easyii\modules\article\models\Item;
use yii\easyii\widgets\Fancybox;
use yii\widgets\LinkPager;

class Article extends \yii\easyii\components\API
{
    private $_cats;
    private $_items;
    private $_adp;
    private $_item = [];
    private $_last;

    public function api_cat($id_slug)
    {
        if(!isset($this->_cats[$id_slug])) {
            $this->_cats[$id_slug] = $this->findCategory($id_slug);
        }
        return $this->_cats[$id_slug];
    }

    public function api_tree()
    {
        return Category::tree();
    }

    public function api_cats()
    {
        return Category::cats();
    }

    public function api_items($options = [])
    {
        if(!$this->_items){
            $this->_items = [];

            $query = Item::find()->with(['seo', 'category'])->status(Item::STATUS_ON);

            if(!empty($options['where'])){
                $query->andFilterWhere($options['where']);
            }
            if(!empty($options['orderBy'])){
                $query->orderBy($options['orderBy']);
            } else {
                $query->sortDate();
            }

            $this->_adp = new ActiveDataProvider([
                'query' => $query,
                'pagination' => !empty($options['pagination']) ? $options['pagination'] : []
            ]);

            foreach($this->_adp->models as $model){
                $this->_items[] = new ArticleObject($model);
            }
        }
        return $this->_items;
    }

    public function api_last($limit = 1, $where = null)
    {
        if($limit === 1 && $this->_last){
            return $this->_last;
        }

        $result = [];

        $query = Item::find()->with('seo')->status(Item::STATUS_ON)->sortDate()->limit($limit);
        if($where){
            $query->andFilterWhere($where);
        }

        foreach($query->all() as $item){
            $result[] = new ArticleObject($item);
        }

        if($limit > 1){
            return $result;
        }else{
            $this->_last = count($result) ? $result[0] : null;
            return $this->_last;
        }
    }

    public function api_get($id_slug)
    {
        if(!isset($this->_item[$id_slug])) {
            $this->_item[$id_slug] = $this->findItem($id_slug);
        }
        return $this->_item[$id_slug];
    }

    public function api_plugin($options = [])
    {
        Fancybox::widget([
            'selector' => '.easyii-box',
            'options' => $options
        ]);
    }

    public function api_pagination()
    {
        return $this->_adp ? $this->_adp->pagination : null;
    }

    public function api_pages()
    {
        return $this->_adp ? LinkPager::widget(['pagination' => $this->_adp->pagination]) : '';
    }

    private function findCategory($id_slug)
    {
        $category = Category::find()->where(['or', 'category_id=:id_slug', 'slug=:id_slug'], [':id_slug' => $id_slug])->status(Item::STATUS_ON)->one();

        return $category ? new CategoryObject($category) : null;
    }

    private function findItem($id_slug)
    {
        $article = Item::find()->where(['or', 'item_id=:id_slug', 'slug=:id_slug'], [':id_slug' => $id_slug])->status(Item::STATUS_ON)->one();
        if($article) {
            $article->updateCounters(['views' => 1]);
            return new ArticleObject($article);
        } else {
            return null;
        }
    }
}