<?php

namespace netis\utils\db;

use yii\data\ActiveDataProvider;
use yii\data\Pagination;
use yii\data\Sort;

/**
 * Description here...
 *
 * @author Michał Motyczko <michal@motyczko.pl>
 */
interface ActiveSearchInterface
{
    /**
     * Creates data provider instance with search query applied.
     *
     * The following keys of $params should be supported:
     * * ids - one or more primary key values
     * * search - a search phrase applied to all possible attributes (if type matches)
     * * query - one or more named queries, if the $query object supports them
     * * model class name - a search form with fields for model attributes
     *
     * @param array $params
     * @param \yii\db\ActiveQuery $query
     * @param Sort|array $sort
     * @param Pagination|array $pagination
     * @return ActiveDataProvider
     */
    public function search($params, \yii\db\ActiveQuery $query = null, $sort = [], $pagination = []);

    /**
     * @inheritdoc
     * HasOne relations are never safe, even if they have validation rules.
     */
    public function safeAttributes();

    /**
     * Assigns specified token to specified attributes and validates
     * current model to filter the values. Then, creates search condition.
     * @param  string $token     one search token extracted from whole term
     * @param  array $attributes attributes safe to search
     * @param  string $tablePrefix
     * @return array all conditions joined with OR operator, should be merged with main query object
     */
    public function processSearchToken($token, array $attributes, $tablePrefix = null);
}
