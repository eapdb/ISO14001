<?php
require_once 'config.php';
check_login();

$edit_mode = false;
$assessment_id = null;
$assessment = null;

// 檢查是否為編輯模式
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $edit_mode = true;
    $assessment_id = (int)$_GET['id'];
    
    $stmt = $conn->prepare("SELECT * FROM regulation_assessments WHERE id = ?");
    $stmt->bind_param("i", $assessment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $assessment = $result->fetch_assoc();
    $stmt->close();
    
    if (!$assessment) {
        set_error_message('評估不存在');
        header('Location: assessment_list.php');
        exit();
    }
}

// 處理表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_error_message('Invalid request');
        header('Location: assessment_list.php');
        exit();
    }
    
    $conn->begin_transaction();
    
    try {
        // 基本資料
        $regulation_name = clean_input($_POST['regulation_name']);
        $regulation_number = clean_input($_POST['regulation_number']);
        $effective_date = $_POST['effective_date'];
        $assessment_date = $_POST['assessment_date'];
        $status = $_POST['status'];
        
        if ($edit_mode) {
            // 更新
            $stmt = $conn->prepare("UPDATE regulation_assessments SET regulation_name=?, regulation_number=?, effective_date=?, assessment_date=?, status=? WHERE id=?");
            $stmt->bind_param("sssssi", $regulation_name, $regulation_number, $effective_date, $assessment_date, $status, $assessment_id);
            $stmt->execute();
            $stmt->close();
        } else {
            // 新增
            $stmt = $conn->prepare("INSERT INTO regulation_assessments (regulation_name, regulation_number, effective_date, assessment_date, status, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssi", $regulation_name, $regulation_number, $effective_date, $assessment_date, $status, $_SESSION['user_id']);
            $stmt->execute();
            $assessment_id = $conn->insert_id;
            $stmt->close();
        }
        
        // 刪除舊的必要性分析
        $conn->query("DELETE FROM necessity_analysis WHERE assessment_id = $assessment_id");
        
        // 插入必要性分析
        if (!empty($_POST['problem_description'])) {
            $stmt = $conn->prepare("INSERT INTO necessity_analysis (assessment_id, problem_description, legal_basis, necessity_justification) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $assessment_id, $_POST['problem_description'], $_POST['legal_basis'], $_POST['necessity_justification']);
            $stmt->execute();
            $stmt->close();
        }
        
        // 刪除舊的替代方案
        $conn->query("DELETE FROM alternatives WHERE assessment_id = $assessment_id");
        
        // 插入替代方案
        if (isset($_POST['alt_names'])) {
            $stmt = $conn->prepare("INSERT INTO alternatives (assessment_id, alternative_name, description, advantages, disadvantages, feasibility_score) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($_POST['alt_names'] as $i => $alt_name) {
                if (!empty($alt_name)) {
                    $desc = $_POST['alt_descriptions'][$i] ?? '';
                    $adv = $_POST['alt_advantages'][$i] ?? '';
                    $disadv = $_POST['alt_disadvantages'][$i] ?? '';
                    $score = (int)($_POST['alt_scores'][$i] ?? 3);
                    $stmt->bind_param("issssi", $assessment_id, $alt_name, $desc, $adv, $disadv, $score);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }
        
        // 刪除舊的利害關係者
        $conn->query("DELETE FROM stakeholder_assessment WHERE assessment_id = $assessment_id");
        
        // 插入利害關係者
        if (isset($_POST['stakeholder_names'])) {
            $stmt = $conn->prepare("INSERT INTO stakeholder_assessment (assessment_id, stakeholder_name, stakeholder_type, impact_description, impact_level, mitigation_measures) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($_POST['stakeholder_names'] as $i => $sh_name) {
                if (!empty($sh_name)) {
                    $sh_type = $_POST['stakeholder_types'][$i] ?? '';
                    $sh_impact = $_POST['stakeholder_impacts'][$i] ?? '';
                    $sh_level = $_POST['stakeholder_levels'][$i] ?? 'medium';
                    $sh_mitigation = $_POST['stakeholder_mitigations'][$i] ?? '';
                    $stmt->bind_param("isssss", $assessment_id, $sh_name, $sh_type, $sh_impact, $sh_level, $sh_mitigation);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }
        
        // 刪除舊的成本效益
        $conn->query("DELETE FROM cost_benefit_analysis WHERE assessment_id = $assessment_id");
        
        // 插入成本效益
        if (isset($_POST['cb_names'])) {
            $stmt = $conn->prepare("INSERT INTO cost_benefit_analysis (assessment_id, item_name, item_type, category, amount, description, calculation_basis) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($_POST['cb_names'] as $i => $cb_name) {
                if (!empty($cb_name)) {
                    $cb_type = $_POST['cb_types'][$i] ?? 'cost';
                    $cb_category = $_POST['cb_categories'][$i] ?? '';
                    $cb_amount = (float)($_POST['cb_amounts'][$i] ?? 0);
                    $cb_desc = $_POST['cb_descriptions'][$i] ?? '';
                    $cb_basis = $_POST['cb_bases'][$i] ?? '';
                    $stmt->bind_param("isssdss", $assessment_id, $cb_name, $cb_type, $cb_category, $cb_amount, $cb_desc, $cb_basis);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }
        
        // 刪除舊的影響評估
        $conn->query("DELETE FROM impact_evaluation WHERE assessment_id = $assessment_id");
        
        // 插入影響評估
        if (isset($_POST['impact_types'])) {
            $stmt = $conn->prepare("INSERT INTO impact_evaluation (assessment_id, impact_type, impact_description, severity_level, evidence) VALUES (?, ?, ?, ?, ?)");
            foreach ($_POST['impact_types'] as $i => $imp_type) {
                if (!empty($_POST['impact_descriptions'][$i])) {
                    $imp_desc = $_POST['impact_descriptions'][$i];
                    $imp_severity = $_POST['impact_severities'][$i] ?? 'neutral';
                    $imp_evidence = $_POST['impact_evidences'][$i] ?? '';
                    $stmt->bind_param("issss", $assessment_id, $imp_type, $imp_desc, $imp_severity, $imp_evidence);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }
        
        $conn->commit();
        set_success_message($edit_mode ? '評估已成功更新' : '評估已成功建立');
        header('Location: assessment_detail.php?id=' . $assessment_id);
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        set_error_message('儲存失敗: ' . $e->getMessage());
    }
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $edit_mode ? '編輯' : '新增'; ?>評估 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <script>
        function addAlternative() {
            const container = document.getElementById('alternatives-container');
            const index = container.children.length;
            const html = `
                <div class="card" style="margin-bottom: 15px;">
                    <div class="card-body">
                        <h4>替代方案 ${index + 1}</h4>
                        <div class="form-group">
                            <label>方案名稱</label>
                            <input type="text" name="alt_names[]" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>方案說明</label>
                            <textarea name="alt_descriptions[]" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <label>優點</label>
                            <textarea name="alt_advantages[]" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <label>缺點</label>
                            <textarea name="alt_disadvantages[]" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <label>可行性評分 (1-5)</label>
                            <input type="number" name="alt_scores[]" min="1" max="5" value="3">
                        </div>
                    </div>
                </div>`;
            container.insertAdjacentHTML('beforeend', html);
        }
        
        function addStakeholder() {
            const container = document.getElementById('stakeholders-container');
            const index = container.children.length;
            const html = `
                <div class="card" style="margin-bottom: 15px;">
                    <div class="card-body">
                        <h4>利害關係者 ${index + 1}</h4>
                        <div class="form-group">
                            <label>名稱</label>
                            <input type="text" name="stakeholder_names[]">
                        </div>
                        <div class="form-group">
                            <label>類型</label>
                            <input type="text" name="stakeholder_types[]" placeholder="例如：企業、民間團體、政府機關">
                        </div>
                        <div class="form-group">
                            <label>影響描述</label>
                            <textarea name="stakeholder_impacts[]" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <label>影響程度</label>
                            <select name="stakeholder_levels[]">
                                <option value="high">高</option>
                                <option value="medium" selected>中</option>
                                <option value="low">低</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>減緩措施</label>
                            <textarea name="stakeholder_mitigations[]" rows="2"></textarea>
                        </div>
                    </div>
                </div>`;
            container.insertAdjacentHTML('beforeend', html);
        }
        
        function addCostBenefit() {
            const container = document.getElementById('costbenefit-container');
            const index = container.children.length;
            const html = `
                <div class="card" style="margin-bottom: 15px;">
                    <div class="card-body">
                        <h4>項目 ${index + 1}</h4>
                        <div class="form-group">
                            <label>項目名稱</label>
                            <input type="text" name="cb_names[]">
                        </div>
                        <div class="form-group">
                            <label>類型</label>
                            <select name="cb_types[]">
                                <option value="cost">成本</option>
                                <option value="benefit">效益</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>類別</label>
                            <input type="text" name="cb_categories[]" placeholder="例如：人力成本、環境效益">
                        </div>
                        <div class="form-group">
                            <label>金額</label>
                            <input type="number" name="cb_amounts[]" step="0.01" value="0">
                        </div>
                        <div class="form-group">
                            <label>說明</label>
                            <textarea name="cb_descriptions[]" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <label>計算依據</label>
                            <textarea name="cb_bases[]" rows="2"></textarea>
                        </div>
                    </div>
                </div>`;
            container.insertAdjacentHTML('beforeend', html);
        }
        
        function addImpact() {
            const container = document.getElementById('impacts-container');
            const index = container.children.length;
            const html = `
                <div class="card" style="margin-bottom: 15px;">
                    <div class="card-body">
                        <h4>影響評估 ${index + 1}</h4>
                        <div class="form-group">
                            <label>影響類型</label>
                            <select name="impact_types[]">
                                <option value="economic">經濟</option>
                                <option value="social">社會</option>
                                <option value="environmental">環境</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>影響描述</label>
                            <textarea name="impact_descriptions[]" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <label>嚴重程度</label>
                            <select name="impact_severities[]">
                                <option value="positive_high">正面影響-高</option>
                                <option value="positive_medium">正面影響-中</option>
                                <option value="positive_low">正面影響-低</option>
                                <option value="neutral" selected>中性</option>
                                <option value="negative_low">負面影響-低</option>
                                <option value="negative_medium">負面影響-中</option>
                                <option value="negative_high">負面影響-高</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>證據資料</label>
                            <textarea name="impact_evidences[]" rows="2"></textarea>
                        </div>
                    </div>
                </div>`;
            container.insertAdjacentHTML('beforeend', html);
        }
    </script>
</head>
<body>
    <div class="container">
        <nav class="navbar">
            <h1><?php echo SITE_NAME; ?></h1>
            <div class="nav-links">
                <a href="index.php">主控台</a>
                <a href="assessment_list.php">評估清單</a>
                <a href="consultation.php">公開諮詢</a>
                <a href="change_password.php">修改密碼</a>
                <a href="logout.php">登出</a>
            </div>
            <div class="user-info">
                <span>使用者: <?php echo htmlspecialchars($_SESSION['username']); ?></span>
            </div>
        </nav>
        
        <div class="content">
            <h2><?php echo $edit_mode ? '編輯' : '新增'; ?>法規評估</h2>
            
            <?php echo display_messages(); ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <!-- 基本資料 -->
                <div class="card">
                    <div class="card-header">
                        <h3>基本資料</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>法規名稱 *</label>
                            <input type="text" name="regulation_name" required value="<?php echo htmlspecialchars($assessment['regulation_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>法規編號</label>
                            <input type="text" name="regulation_number" value="<?php echo htmlspecialchars($assessment['regulation_number'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>生效日期</label>
                            <input type="date" name="effective_date" value="<?php echo $assessment['effective_date'] ?? ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>評估日期 *</label>
                            <input type="date" name="assessment_date" required value="<?php echo $assessment['assessment_date'] ?? date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>狀態</label>
                            <select name="status">
                                <option value="draft" <?php echo ($assessment['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>草稿</option>
                                <option value="in_review" <?php echo ($assessment['status'] ?? '') === 'in_review' ? 'selected' : ''; ?>>審核中</option>
                                <option value="completed" <?php echo ($assessment['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>已完成</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- 必要性分析 -->
                <div class="card">
                    <div class="card-header">
                        <h3>必要性分析</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>問題描述</label>
                            <textarea name="problem_description" rows="4" placeholder="描述需要制定或修改此法規的背景問題"><?php echo htmlspecialchars($_POST['problem_description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>法律依據</label>
                            <textarea name="legal_basis" rows="3" placeholder="列出相關法律依據和授權條款"><?php echo htmlspecialchars($_POST['legal_basis'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>必要性論證</label>
                            <textarea name="necessity_justification" rows="4" placeholder="說明為何此法規是解決問題的必要手段"><?php echo htmlspecialchars($_POST['necessity_justification'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- 替代方案 -->
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3>替代方案評估</h3>
                        <button type="button" onclick="addAlternative()" class="btn btn-secondary" style="padding: 8px 16px;">新增方案</button>
                    </div>
                    <div class="card-body">
                        <div id="alternatives-container"></div>
                        <p style="color: #7f8c8d; font-size: 14px;">點擊「新增方案」按鈕來新增替代方案</p>
                    </div>
                </div>
                
                <!-- 利害關係者評估 -->
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3>利害關係者評估</h3>
                        <button type="button" onclick="addStakeholder()" class="btn btn-secondary" style="padding: 8px 16px;">新增利害關係者</button>
                    </div>
                    <div class="card-body">
                        <div id="stakeholders-container"></div>
                        <p style="color: #7f8c8d; font-size: 14px;">點擊「新增利害關係者」按鈕來新增評估對象</p>
                    </div>
                </div>
                
                <!-- 成本效益分析 -->
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3>成本效益分析</h3>
                        <button type="button" onclick="addCostBenefit()" class="btn btn-secondary" style="padding: 8px 16px;">新增項目</button>
                    </div>
                    <div class="card-body">
                        <div id="costbenefit-container"></div>
                        <p style="color: #7f8c8d; font-size: 14px;">點擊「新增項目」按鈕來新增成本或效益項目</p>
                    </div>
                </div>
                
                <!-- 影響評估 -->
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3>經濟、社會與環境影響評估</h3>
                        <button type="button" onclick="addImpact()" class="btn btn-secondary" style="padding: 8px 16px;">新增影響評估</button>
                    </div>
                    <div class="card-body">
                        <div id="impacts-container"></div>
                        <p style="color: #7f8c8d; font-size: 14px;">點擊「新增影響評估」按鈕來評估對經濟、社會或環境的影響</p>
                    </div>
                </div>
                
                <!-- 操作按鈕 -->
                <div class="action-buttons">
                    <button type="submit" class="btn btn-primary">儲存評估</button>
                    <a href="assessment_list.php" class="btn btn-secondary">取消</a>
                </div>
            </form>
        </div>
        
        <div class="footer">
            <p>&copy; 2026 <?php echo SITE_NAME; ?>. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
