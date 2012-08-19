<p><?= \Fuel\Core\Session::get_flash('result') ?></p>
<div id="db_contents">
<?= isset($error) ? $error : '' ?>
<?= str_replace('required="required"', '',$form) ?>
</div>