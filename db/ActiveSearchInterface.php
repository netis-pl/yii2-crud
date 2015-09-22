<?php

namespace netis\utils\db;

/**
 * Description here...
 *
 * @author Michał Motyczko <michal@motyczko.pl>
 */
interface ActiveSearchInterface
{
    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
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
