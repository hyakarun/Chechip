<?php
// admin_generate_exp.php
session_start();
// 管理者ログインをチェック
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: index.php');
    exit();
}
$max_level_default = 1000; // 最大レベルのデフォルト値
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>経験値テーブル管理</title>
    <style>
        body { font-family: sans-serif; }
        .container { max-width: 800px; margin: 20px auto; }
        .form-group { margin-bottom: 15px; padding: 10px; border: 1px solid #ccc; background-color: #f9f9f9; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="number"] { width: 150px; padding: 5px; }
        button { padding: 8px 12px; cursor: pointer; }
        #breakpoints-container .form-group { display: flex; align-items: center; gap: 10px; }
        .success-msg { color: green; font-weight: bold; padding: 10px; background-color: #e6ffe6; border: 1px solid green; }
        .danger-zone { border: 2px solid #cc0000; padding: 15px; background-color: #fff0f0; }
        .danger-zone h3 { color: #cc0000; margin-top: 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>経験値テーブル管理</h1>
        <p>
            <a href="dashboard.php">ダッシュボード</a> | 
            <a href="admin_monsters.php">モンスター管理</a> | 
            <a href="logout.php">ログアウト</a>
        </p>
        <hr>

        <?php if (isset($_GET['success'])): ?>
            <p class="success-msg">経験値テーブルの再構築が完了しました。</p>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <p style="color:red; font-weight: bold;"><?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <div class="danger-zone">
            <h3><span style="font-size: 1.5em;">⚠️</span> 実行注意</h3>
            <p>このツールを実行すると、`experience_table` のデータが**すべて削除**され、新しい計算式で**上書き**されます。<br>
            実行前に、必ずバックアップを取るか、設定値が正しいことを確認してください。</p>
            
            <form action="admin_handle_generate_exp.php" method="post" onsubmit="return confirm('本当に `experience_table` を上書きしますか？この操作は元に戻せません。');">
                
                <div class="form-group">
                    <label for="max_level">生成する最大レベル (上限)</label>
                    <input type="number" id="max_level" name="max_level" value="<?php echo $max_level_default; ?>" min="2" required>
                </div>

                <div class="form-group">
                    <label for="initial_exp">Lv 1 → 2 に必要な経験値 (初期値)</label>
                    <input type="number" id="initial_exp" name="initial_exp" value="10" min="1" required>
                </div>

                <hr>
                <p><strong>レベルアップごとの「区間経験値」の増加量設定:</strong></p>

                <div id="breakpoints-container">
                    <div class="form-group">
                        <span>Lv 2 から</span>
                        <input type="number" name="level_breakpoint[]" value="100" min="3" placeholder="レベル (X) まで" required>
                        <span>まで、必要経験値</span>
                        <input type="number" name="exp_increase[]" value="20" placeholder="(Y) ずつ増加" required>
                        <span>ずつ増加</span>
                        <button type="button" onclick="removeBreakpoint(this)">削除</button>
                    </div>
                    <div class="form-group">
                        <span>Lv 101 から</span>
                        <input type="number" name="level_breakpoint[]" value="500" min="101" placeholder="レベル (X) まで" required>
                        <span>まで、必要経験値</span>
                        <input type="number" name="exp_increase[]" value="100" placeholder="(Y) ずつ増加" required>
                        <span>ずつ増加</span>
                        <button type="button" onclick="removeBreakpoint(this)">削除</button>
                    </div>
                </div>
                
                <button type="button" onclick="addBreakpoint()">＋ 段階を追加する</button>

                <hr style="margin-top: 20px;">
                <button type="submit" style="background-color: #cc0000; color: white; padding: 15px; font-size: 1.2em; font-weight: bold;">
                    経験値テーブルを再構築する
                </button>
            </form>
        </div>
    </div>

    <script>
        function addBreakpoint() {
            const container = document.getElementById('breakpoints-container');
            const newGroup = document.createElement('div');
            newGroup.className = 'form-group';
            
            // 最後のブレークポイントのレベルを取得
            const lastLevelInput = container.querySelector('input[name="level_breakpoint[]"]:last-of-type');
            const lastLevel = lastLevelInput ? parseInt(lastLevelInput.value) : 1;
            const nextStartLevel = lastLevel + 1;

            newGroup.innerHTML = `
                <span>Lv ${nextStartLevel} から</span>
                <input type="number" name="level_breakpoint[]" value="${nextStartLevel + 99}" min="${nextStartLevel}" placeholder="レベル (X) まで" required>
                <span>まで、必要経験値</span>
                <input type="number" name="exp_increase[]" value="200" placeholder="(Y) ずつ増加" required>
                <span>ずつ増加</span>
                <button type="button" onclick="removeBreakpoint(this)">削除</button>
            `;
            container.appendChild(newGroup);
        }

        function removeBreakpoint(button) {
            // 最後の1個は削除させない
            if (document.querySelectorAll('#breakpoints-container .form-group').length <= 1) {
                alert('最低1つの段階が必要です。');
                return;
            }
            button.closest('.form-group').remove();
        }
    </script>
</body>
</html>