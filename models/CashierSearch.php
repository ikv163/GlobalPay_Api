<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * CashierSearch represents the model behind the search form of `app\models\Cashier`.
 */
class CashierSearch extends Cashier
{

    public $starttime;
    public $endtime;
    public $page;
    public $pageSize;
    public $child_name;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['income', 'security_money', 'wechat_rate', 'alipay_rate', 'union_pay_rate', 'bank_card_rate', 'wechat_amount', 'alipay_amount', 'union_pay_amount', 'bank_card_amount'], 'number'],
            [['priority', 'agent_class', 'cashier_status'], 'integer'],
            [['insert_at', 'update_at', 'login_at'], 'safe'],
            [['username', 'child_name', 'parent_name', 'wechat', 'alipay'], 'string', 'max' => 50],
            [['login_password', 'pay_password', 'remark'], 'string', 'max' => 255],
            [['telephone'], 'string', 'max' => 20],
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
    public function search($params, $username)
    {
        $query = Cashier::find()->select(['id', 'username', 'income', 'security_money', 'wechat_rate', 'alipay_rate', 'union_pay_rate', 'bank_card_rate', 'wechat_amount', 'alipay_amount', 'union_pay_amount', 'bank_card_amount', 'wechat', 'alipay', 'telephone', 'agent_class', 'invite_code', 'cashier_status', 'insert_at', 'login_at', 'remark'])->where(['parent_name' => $username])->andWhere(['<', 'cashier_status', 2]);

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query
        ]);

        //$this->load($params);
        $this->setAttributes($params);


        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            //return $dataProvider;
            $errors = $this->getFirstErrors();
            return reset($errors);
        }
        $this->page = isset($this->page) && is_numeric($this->page) && $this->page > 0 ? intval($this->page) : 1;
        $this->pageSize = isset($this->pageSize) && is_numeric($this->pageSize) && $this->pageSize > 0 ? intval($this->pageSize) : 2000;

        // grid filtering conditions
        $query->andFilterWhere([
            'cashier_status' => $this->cashier_status,
            'username' => $this->child_name,
            'wechat' => $this->wechat,
            'alipay' => $this->alipay,
            'telephone' => $this->telephone,
        ]);

        $query->andFilterWhere(['>=', 'insert_at', $this->starttime])
            ->andFilterWhere(['<=', 'insert_at', $this->endtime]);

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
            'data' => $query->asArray()->all(),
        );

    }
}
