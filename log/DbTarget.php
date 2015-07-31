<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\log;

use Yii;
use yii\helpers\VarDumper;

/**
 * DbTarget stores log messages in a database table.
 *
 * The database connection is specified by [[db]]. Database schema could be initialized by applying migration:
 *
 * ```
 * yii migrate --migrationPath=@netis/utils/log/migrations/
 * ```
 *
 * If you don't want to use migration and need SQL instead, files for all databases are in migrations directory.
 *
 * You may change the name of the table used to store the data by setting [[logTable]].
 *
 * @author Jan Waś <jwas@netis.pl>
 */
class DbTarget extends \yii\log\DbTarget
{
    /**
     * @var bool
     */
    public $useBatchInsert = true;

    public function getPrefixData()
    {
        $ip = null;
        /** @var \yii\web\Request $request */
        $request = Yii::$app->getRequest();
        if (($request = Yii::$app->getRequest()) instanceof \yii\web\Request) {
            $ip = ip2long($request->getUserIP());
            if ($ip > 0x7FFFFFFF) {
                $ip -= (0xFFFFFFFF + 1);
            }
        }

        /* @var $user \yii\web\User */
        $user = Yii::$app->has('user', true) ? Yii::$app->get('user') : null;
        if ($user && ($identity = $user->getIdentity(false))) {
            $userID = $identity->getId();
        } else {
            $userID = null;
        }

        /* @var $session \yii\web\Session */
        $session = Yii::$app->has('session', true) ? Yii::$app->get('session') : null;
        $sessionID = $session && $session->getIsActive() ? $session->getId() : null;
        return [$ip, $userID, $sessionID];
    }

    /**
     * Stores log messages to DB.
     */
    public function export()
    {
        $tableName = $this->db->quoteTableName($this->logTable);
        if ($this->useBatchInsert) {
            $sql = $this->db->getQueryBuilder()->batchInsert($this->logTable, [
                'level', 'category', 'log_time', 'ip', 'user_id', 'session_id', 'prefix', 'message',
            ], array_map('array_values', array_map([$this, 'messageToRow'], $this->messages)));
        } else {
            $sql = "INSERT INTO $tableName ([[level]], [[category]], [[log_time]], [[ip]], [[user_id]], [[session_id]],
                [[prefix]], [[message]])
                VALUES (:level, :category, :log_time, :ip, :user_id, :session_id, :prefix, :message)";
        }
        $command = $this->db->createCommand($sql);
        if ($this->db->getTransaction() === null) {
            $trx = $this->db->beginTransaction();
        }
        if ($this->useBatchInsert) {
            $command->execute();
        } else {
            foreach ($this->messages as $message) {
                $command->bindValues($this->messageToRow($message))->execute();
            }
        }
        if (isset($trx)) {
            $trx->commit();
        }
    }

    private function messageToRow($message)
    {
        list($text, $level, $category, $timestamp) = $message;
        if (!is_string($text)) {
            // don't use VarDumper::export($text) as it is not safe with references
            $text = gettype($text);
        }

        list($ip, $userID, $sessionID) = $this->getPrefixData();

        return [
            ':level' => $level,
            ':category' => $category,
            ':log_time' => $timestamp,
            ':ip' => $ip,
            ':user_id' => $userID,
            ':session_id' => $sessionID,
            ':prefix' => $this->getMessagePrefix($message),
            ':message' => $text,
        ];
    }
}
