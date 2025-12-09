<?php
/**
 * UserCodeGenerator - 利用者コード自動生成クラス
 * 
 * 企業コード + 連番4桁の利用者コードを自動生成
 * 例: ABC0001, ABC0002, ...
 * 
 * @package Smiley配食事業システム
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';

class UserCodeGenerator {
    private $db;
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 企業IDから利用者コードを自動生成
     * 
     * @param int $companyId 企業ID
     * @return string 利用者コード（例: ABC0001）
     * @throws Exception
     */
    public function generateUserCode($companyId) {
        // 企業コード取得
        $company = $this->getCompanyCode($companyId);
        
        if (!$company) {
            throw new Exception("企業が見つかりません");
        }
        
        $companyCode = $company['company_code'];
        
        // 同じ企業の既存利用者数を取得
        $count = $this->getCompanyUserCount($companyId);
        
        // 連番を生成（4桁、ゼロ埋め）
        $sequenceNumber = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        
        // 利用者コード生成
        $userCode = $companyCode . $sequenceNumber;
        
        // 重複チェック（念のため）
        $maxAttempts = 100;
        $attempt = 0;
        
        while ($this->userCodeExists($userCode) && $attempt < $maxAttempts) {
            $attempt++;
            $sequenceNumber = str_pad($count + 1 + $attempt, 4, '0', STR_PAD_LEFT);
            $userCode = $companyCode . $sequenceNumber;
        }
        
        if ($attempt >= $maxAttempts) {
            throw new Exception("利用者コードの生成に失敗しました");
        }
        
        return $userCode;
    }
    
    /**
     * 企業コードを取得
     * 
     * @param int $companyId 企業ID
     * @return array|null 企業情報
     */
    private function getCompanyCode($companyId) {
        $sql = "SELECT company_code FROM companies WHERE id = :id";
        return $this->db->fetch($sql, ['id' => $companyId]);
    }
    
    /**
     * 企業の既存利用者数を取得
     * 
     * @param int $companyId 企業ID
     * @return int 利用者数
     */
    private function getCompanyUserCount($companyId) {
        $sql = "SELECT COUNT(*) FROM users WHERE company_id = :company_id";
        return (int)$this->db->fetchColumn($sql, ['company_id' => $companyId]);
    }
    
    /**
     * 利用者コードの存在確認
     * 
     * @param string $userCode 利用者コード
     * @return bool 存在する場合true
     */
    private function userCodeExists($userCode) {
        $sql = "SELECT COUNT(*) FROM users WHERE user_code = :user_code";
        $count = $this->db->fetchColumn($sql, ['user_code' => $userCode]);
        return $count > 0;
    }
    
    /**
     * パスワードをハッシュ化
     * 
     * @param string $password 平文パスワード
     * @return string ハッシュ化されたパスワード
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * パスワードの強度チェック
     * 
     * @param string $password パスワード
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validatePassword($password) {
        $errors = [];
        
        // 長さチェック（8文字以上）
        if (strlen($password) < 8) {
            $errors[] = 'パスワードは8文字以上で入力してください';
        }
        
        // 英字を含むかチェック
        if (!preg_match('/[a-zA-Z]/', $password)) {
            $errors[] = 'パスワードには英字を含めてください';
        }
        
        // 数字を含むかチェック
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'パスワードには数字を含めてください';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
