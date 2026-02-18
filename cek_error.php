<?php
// Script Diagnosa JSON
require 'config.php';

echo "<h1>Alat Diagnosa Project Cost</h1>";
echo "<p>Silakan klik salah satu link Group di bawah ini untuk melihat data mentahnya.</p>";

$groups = $pdo->query("SELECT * FROM cost_groups")->fetchAll();

echo "<ul>";
foreach($groups as $g){
    echo "<li><a href='get_dynamic_form.php?action=get_full_group_structure&group_id=".$g['id']."' target='_blank'>Cek Data Group: <strong>".$g['group_name']."</strong></a></li>";
}
echo "</ul>";
?>