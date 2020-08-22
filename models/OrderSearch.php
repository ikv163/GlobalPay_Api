<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use app\models\Order;

/**
 * OrderSearch represents the model behind the search form of `app\models\Order`.
 */
class OrderSearch extends Order
{

    public $starttime;
    public $endtime;
    public $startamount;
    public $endamount;
    public $page;
    public $pageSize;
    public $qr_nickname;
    public $qr_account;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'order_type', 'order_status', 'notify_status', 'page', 'pageSize'], 'integer'],
            [['order_id', 'query_team', 'mch_order_id', 'username', 'qr_code', 'mch_name', 'callback_url', 'notify_url', 'expire_time', 'read_remark', 'insert_at', 'update_at', 'operator', 'startamount', 'endamount', 'starttime', 'endtime', 'page', 'pageSize', 'qr_account', 'qr_nickname'], 'safe'],
            [['order_fee', 'order_amount', 'benefit', 'actual_amount', 'startamount', 'endamount'], 'number'],
            [['starttime', 'endtime'], 'date', 'format' => 'php:Y-m-d H:i:s'],
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
        $query = Order::find()->select(['order.*', 'qr_code.qr_nickname', 'qr_code.qr_account', 'refund.refund_status', 'refund.refund_type'])->leftJoin('qr_code', 'order.qr_code = qr_code.qr_code')->leftJoin('refund', 'order.order_id = refund.order_id')->orderBy(['order.id' => SORT_DESC]);

        $username = array($params['username']);

        if (isset($params['include_followers']) && $params['include_followers'] == 1) {
            $followers = Cashier::getFollowers($params['username']);
            $followers = $followers ? array_column($followers, 'username') : array();
            $username = array_unique(array_merge($username, $followers));
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query
        ]);

        $this->setAttributes($params);


        if (!$this->validate()) {
            $errors = $this->getFirstErrors();
            return reset($errors);
        }

        $this->page = isset($this->page) && is_numeric($this->page) && $this->page > 0 ? intval($this->page) : 1;
        $this->pageSize = isset($this->pageSize) && is_numeric($this->pageSize) && $this->pageSize > 0 ? intval($this->pageSize) : 10;

        //订单状态传0 则代表查询所有状态的订单
        if ($this->order_status != 0) {
            $query->andWhere(['order.order_status' => $this->order_status]);
        }

        $query->andFilterWhere([
            'order.order_type' => $this->order_type,
        ]);

        $query->andFilterWhere(['like', 'order.order_id', $this->order_id])
            ->andFilterWhere(['like', 'order.mch_order_id', $this->mch_order_id])
            ->andFilterWhere(['in', 'order.username', $username])
            ->andFilterWhere(['like', 'order.qr_code', $this->qr_code])
            ->andFilterWhere(['like', 'order.mch_name', $this->mch_name])
            ->andFilterWhere(['>=', 'order.insert_at', $this->starttime])
            ->andFilterWhere(['<=', 'order.insert_at', $this->endtime])
            ->andFilterWhere(['>=', 'order.order_amount', $this->startamount])
            ->andFilterWhere(['<=', 'order.order_amount', $this->endamount]);

        $totalCount = $dataProvider->totalCount;
        $maxPage = ceil($totalCount / $this->pageSize);
        $this->page = $this->page > $maxPage ? $maxPage : $this->page;

        $limit = $this->pageSize;
        $offset = ($this->page - 1) * $this->pageSize;
        $query->limit($limit)->offset($offset);

        return array(
            'page' => $this->page,
            'pageSize' => $this->pageSize,
            'totalPage' => $maxPage,
            'total' => $totalCount,
            'data' => $query->asArray()->all(),
        );
    }

    public function teamIncome($params, $type = 0)
    {
        $query = Order::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        //查询下级
        $next = [];
        if ($this->username && empty($this->query_team)) {
            $query->andFilterWhere(['=', 'order.username', $this->username]);
        } elseif ($this->username && $this->query_team) {
            array_push($next, $this->username);
            $team = Cashier::calcTeam(Cashier::findOne(['username' => $this->username]));
            if ($team) {
                //直接下级
                if ($this->query_team == 1) {
                    foreach ($team as $v) {
                        if ($v['parent_name'] == $this->username) {
                            array_push($next, $v['username']);
                        }
                    }
                } else {
                    //所有下级
                    foreach ($team as $v) {
                        array_push($next, $v['username']);
                    }
                }
            }
        }

        if (count($next)) {
            $query->andFilterWhere(['in', 'order.username', $next]);
        }

        if ($this->order_status == 999) {
            $query->andFilterWhere(['in', 'order.order_status', [2, 5]]);
        } elseif (isset($this->order_status) && !empty($this->order_status)) {
            $query->andFilterWhere(['=', 'order.order_status', $this->order_status]);
        }

        $query->andFilterWhere([
            'order.order_type' => $this->order_type,
        ]);

        if (!isset($this->insert_at_start) || empty($this->insert_at_start)) {
            $this->insert_at_start = date('Y-m-d 00:00:00');
        }
        if (!isset($this->insert_at_end) || empty($this->insert_at_end)) {
            $this->insert_at_end = date('Y-m-d 23:59:59');
        }

        $query->andFilterWhere(['like', 'order.order_id', $this->order_id])
            ->andFilterWhere(['like', 'order.mch_order_id', $this->mch_order_id])
            ->andFilterWhere(['=', 'order.qr_code', $this->qr_code])
            ->andFilterWhere(['=', 'order.mch_name', $this->mch_name])
            ->andFilterWhere(['>=', 'order.insert_at', $this->insert_at_start])
            ->andFilterWhere(['<=', 'order.insert_at', $this->insert_at_end])
            ->andFilterWhere(['like', 'order.operator', $this->operator])
            ->andFilterWhere(['like', 'order.read_remark', $this->read_remark]);
        if ($type == 1) {
            return $query;
        } else {
            return $dataProvider;
        }
    }
}
