-- ISO14001 環境管理系統 - 法規變更衝擊影響評估資料庫
-- 建立日期: 2026-01-02

-- 建立資料庫
CREATE DATABASE IF NOT EXISTS iso14001_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE iso14001_system;

-- 使用者表
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 法規評估表
CREATE TABLE regulation_assessments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    regulation_name VARCHAR(200) NOT NULL,
    regulation_number VARCHAR(100),
    effective_date DATE,
    assessment_date DATE NOT NULL,
    status ENUM('draft', 'in_review', 'completed') DEFAULT 'draft',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_created_by (created_by),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 必要性分析
CREATE TABLE necessity_analysis (
    id INT PRIMARY KEY AUTO_INCREMENT,
    assessment_id INT NOT NULL,
    problem_description TEXT,
    legal_basis TEXT,
    necessity_justification TEXT,
    FOREIGN KEY (assessment_id) REFERENCES regulation_assessments(id) ON DELETE CASCADE,
    INDEX idx_assessment_id (assessment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 替代方案
CREATE TABLE alternatives (
    id INT PRIMARY KEY AUTO_INCREMENT,
    assessment_id INT NOT NULL,
    alternative_name VARCHAR(200),
    description TEXT,
    advantages TEXT,
    disadvantages TEXT,
    feasibility_score INT CHECK (feasibility_score BETWEEN 1 AND 5),
    FOREIGN KEY (assessment_id) REFERENCES regulation_assessments(id) ON DELETE CASCADE,
    INDEX idx_assessment_id (assessment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 利害關係者評估
CREATE TABLE stakeholder_assessment (
    id INT PRIMARY KEY AUTO_INCREMENT,
    assessment_id INT NOT NULL,
    stakeholder_name VARCHAR(200),
    stakeholder_type VARCHAR(100),
    impact_description TEXT,
    impact_level ENUM('high', 'medium', 'low'),
    mitigation_measures TEXT,
    FOREIGN KEY (assessment_id) REFERENCES regulation_assessments(id) ON DELETE CASCADE,
    INDEX idx_assessment_id (assessment_id),
    INDEX idx_impact_level (impact_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 成本效益分析
CREATE TABLE cost_benefit_analysis (
    id INT PRIMARY KEY AUTO_INCREMENT,
    assessment_id INT NOT NULL,
    item_name VARCHAR(200),
    item_type ENUM('cost', 'benefit'),
    category VARCHAR(100),
    amount DECIMAL(15,2),
    description TEXT,
    calculation_basis TEXT,
    FOREIGN KEY (assessment_id) REFERENCES regulation_assessments(id) ON DELETE CASCADE,
    INDEX idx_assessment_id (assessment_id),
    INDEX idx_item_type (item_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 影響評估（經濟、社會、環境）
CREATE TABLE impact_evaluation (
    id INT PRIMARY KEY AUTO_INCREMENT,
    assessment_id INT NOT NULL,
    impact_type ENUM('economic', 'social', 'environmental'),
    impact_description TEXT,
    severity_level ENUM('positive_high', 'positive_medium', 'positive_low', 'neutral', 'negative_low', 'negative_medium', 'negative_high'),
    evidence TEXT,
    FOREIGN KEY (assessment_id) REFERENCES regulation_assessments(id) ON DELETE CASCADE,
    INDEX idx_assessment_id (assessment_id),
    INDEX idx_impact_type (impact_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 公開諮詢
CREATE TABLE public_consultations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    assessment_id INT NOT NULL,
    consultation_title VARCHAR(200),
    start_date DATE,
    end_date DATE,
    consultation_method VARCHAR(100),
    participant_count INT DEFAULT 0,
    summary TEXT,
    FOREIGN KEY (assessment_id) REFERENCES regulation_assessments(id) ON DELETE CASCADE,
    INDEX idx_assessment_id (assessment_id),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 諮詢意見
CREATE TABLE consultation_feedback (
    id INT PRIMARY KEY AUTO_INCREMENT,
    consultation_id INT NOT NULL,
    submitter_name VARCHAR(100),
    submitter_organization VARCHAR(200),
    feedback_content TEXT,
    response TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (consultation_id) REFERENCES public_consultations(id) ON DELETE CASCADE,
    INDEX idx_consultation_id (consultation_id),
    INDEX idx_submitted_at (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 插入預設管理員帳號
-- 帳號: admin
-- 密碼: admin
INSERT INTO users (username, password) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- 插入範例資料（可選）
INSERT INTO regulation_assessments (regulation_name, regulation_number, effective_date, assessment_date, status, created_by) VALUES
('環境影響評估法修正草案', 'ENV-2026-001', '2026-07-01', '2026-01-02', 'draft', 1);

SET @last_assessment_id = LAST_INSERT_ID();

INSERT INTO necessity_analysis (assessment_id, problem_description, legal_basis, necessity_justification) VALUES
(@last_assessment_id, 
'現行環境影響評估法對於新興開發案件的審查標準已不符時代需求，需要更新以因應氣候變遷與環境保護的挑戰。', 
'依據環境基本法第3條及環境影響評估法第1條規定，為預防及減輕開發行為對環境造成不良影響，確保永續發展。',
'因應國際趨勢與國內環保意識提升，現行法規已無法有效管理新型態開發案件，修法有其必要性。');

INSERT INTO alternatives (assessment_id, alternative_name, description, advantages, disadvantages, feasibility_score) VALUES
(@last_assessment_id, '加強現行法規執行力度', '不修改法條內容，強化執行與監督機制', '無需立法程序，執行成本較低', '無法解決法規本身的不足，治標不治本', 2),
(@last_assessment_id, '全面修訂環評法', '重新制定完整的環境影響評估法', '可徹底解決現有問題，建立完善制度', '立法時程長，社會成本高', 4);

INSERT INTO stakeholder_assessment (assessment_id, stakeholder_name, stakeholder_type, impact_description, impact_level, mitigation_measures) VALUES
(@last_assessment_id, '開發業者', '企業', '需符合更嚴格的環評標準，可能增加開發成本與時程', 'high', '提供輔導與過渡期，協助業者適應新制'),
(@last_assessment_id, '環保團體', '民間團體', '有更完善的環境保護機制，提升環境品質', 'medium', '建立溝通平台，納入民間意見'),
(@last_assessment_id, '地方政府', '政府機關', '審查與監督責任增加，需增加人力與預算', 'medium', '提供教育訓練與預算支援');

INSERT INTO cost_benefit_analysis (assessment_id, item_name, item_type, category, amount, description, calculation_basis) VALUES
(@last_assessment_id, '法規宣導與教育訓練', 'cost', '行政成本', 5000000.00, '辦理說明會、訓練課程等', '預估50場次，每場10萬元'),
(@last_assessment_id, '系統建置與維護', 'cost', '資訊成本', 8000000.00, '建立線上申報與審查系統', '系統開發與3年維護費用'),
(@last_assessment_id, '減少環境污染損失', 'benefit', '環境效益', 50000000.00, '降低開發案件對環境的負面影響', '參考過去環境損害案例評估'),
(@last_assessment_id, '提升產業競爭力', 'benefit', '經濟效益', 30000000.00, '促進綠色產業發展', '預估帶動相關產業成長');

INSERT INTO impact_evaluation (assessment_id, impact_type, impact_description, severity_level, evidence) VALUES
(@last_assessment_id, 'environmental', '加強環境保護標準，減少開發行為對生態系統的破壞', 'positive_high', '參考國際環評制度與國內環境監測數據'),
(@last_assessment_id, 'economic', '短期內可能增加企業合規成本，長期有助於永續發展', 'neutral', '產業調查報告與成本效益分析'),
(@last_assessment_id, 'social', '提升民眾環境權益保障，增進社會公平正義', 'positive_medium', '民意調查顯示70%民眾支持加強環保');

-- 完成訊息
SELECT 'Database setup completed successfully!' AS Message;
SELECT 'Default login - Username: admin, Password: admin' AS Notice;
