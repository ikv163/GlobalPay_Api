<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use app\models\FinanceDetail;

/**
 * FinanceDetailSearch represents the model behind the search form of `app\models\FinanceDetail`.
 */
class FinanceDetailSearch extends FinanceDetail
{

    public $starttime;
    public $endtime;
    public $change_amount_start;
    public $change_amount_end;
    public $before_amount_start;
    public $before_amount_end;
    public $after_amount_start;
    public $after_amount_end;
    public $page;
    public $pageSize;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'user_type', 'finance_type', 'page', 'pageSize'], 'integer'],
            [['change_amount', 'before_amount', 'after_amount', 'change_amount_start', 'change_amount_end', 'before_amount_start', 'before_amount_end', 'after_amount_start', 'after_amount_end'], 'number'],
            [['starttime', 'endtime'], 'date', 'format' => 'php:Y-m-d H:i:s'],
            [['username', 'insert_at', 'remark', 'starttime', 'endtime', 'change_amount_start', 'change_amount_end', 'before_amount_start', 'before_amount_end', 'after_amount_start', 'after_amount_end', 'page', 'pageSize'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = FinanceDetail::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->setAttributes($params);

        if (!$this->validate()) {
            $errors = $this->getFirstErrors();
            return reset($errors);
        }

        $this->page = isset($this->page) && is_numeric($this->page) && $this->page > 0 ? intval($this->page) : 1;
        $this->pageSize = isset($this->pageSize) && is_numeric($this->pageSize) && $this->pageSize > 0 ? intval($this->pageSize) : 10;

        if (isset($params['finance_type']) && $params['finance_type'] != 0) {
            $query->andWhere(['finance_type' => $params['finance_type']]);
        }
        $query->andFilterWhere([
            'user_type' => $this->user_type,
            'username' => $this->username,
        ]);

        $query->andFilterWhere(['>=', 'insert_at', $this->starttime])
            ->andFilterWhere(['<=', 'insert_at', $this->endtime])
            ->andFilterWhere(['>=', 'change_amount', $this->change_amount_start])
            ->andFilterWhere(['<=', 'change_amount', $this->change_amount_end])
            ->andFilterWhere(['>=', 'before_amount', $this->before_amount_start])
            ->andFilterWhere(['<=', 'before_amount', $this->before_amount_end])
            ->andFilterWhere(['>=', 'after_amount', $this->after_amount_start])
            ->andFilterWhere(['<=', 'after_amount', $this->after_amount_end]);

        $totalCount = $dataProvider->totalCount;
        $maxPage = ceil($totalCount / $this->pageSize);
        $this->page = $this->page > $maxPage ? $maxPage : $this->page;

        $limit = $this->pageSize;
        $offset = ($this->page - 1) * $this->pageSize;
        $query->limit($limit)->offset($offset);

        $query->orderBy('insert_at DESC');

        return array(
            'page' => $this->page,
            'pageSize' => $this->pageSize,
            'total' => $totalCount,
            'totalPage' => $maxPage,
            'type' => FinanceDetail::$FinanceTypeRel,
            'data' => $query->asArray()->all(),
        );
    }
}
