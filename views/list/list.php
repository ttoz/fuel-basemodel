<?php if (isset($bootstrap_cdn) && $bootstrap_cdn): ?>
<link rel="stylesheet" type="text/css" charset="utf-8" href="//netdna.bootstrapcdn.com/twitter-bootstrap/2.0.4/css/bootstrap-combined.min.css" />
<?php endif ?>
<?php if (isset($jquery_cdn) && $jquery_cdn): ?>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
<?php endif ?>
<div id="db_contents">
<style>
	table.table tr td div.cell,
	table.table tr th div.cell {
		overflow: hidden;
		height: 20px;
	}
	table.table tr.editrow,
	table.table tr.insrow {
		display:none;
	}
	#expander {
		float:right;
		-khtml-user-drag: element;
	}
	#searchbox {
		position: relative;
		vertical-align: top;
	}
	#tcbadge {
		vertical-align: top;
	}
</style>
<p><?= \Fuel\Core\Session::get_flash('result') ?></p>
<table class="table table-striped">
	<caption>
		<div class="pagination">
			<form id="searchform" method="POST">
				<textarea name="word" rows=1 id="searchbox" placeholder="Ctrl+Enterで検索
				書式：カラム名/演算子/値
				繰り返すとAND検索になる
				例：id/>/100/title/like/%日本%
				x件表示する：per_page_x
				xカラムでソート：order_by_x_asc
				xで降順ソート：order_by_x_desc
				ソートも繰り返し可能
				"><?= $search_word ?></textarea>
				<?= $pager ?>
				<span id="tcbadge" class="badge" title="<?= urldecode($_SERVER['REQUEST_URI']) ?>"><?= number_format($total) ?></span>
				<input type="button" id="expander" draggable="true" title="drag to expand" />
			</form>
		</div>
	</caption>
	<thead>
	<tr>
		<th><a class="insbtn" id="edit_0" href="javascript:void(0)">Ins</a></th>
		<?php
		$columns = 0;
		foreach ($cols as $k => $v):
			$columns++;
			if (isset($v['original'])) $k = $v['original'];
			$head = isset($prop[$k]) && isset($prop[$k]['label']) ? $prop[$k]['label'] : $k;
			if (isset($v['label'])) $head = $v['label'];
			$ord = 'asc';
			if (preg_match('%/order_by_(\w+)_(asc|desc)/%', $_SERVER['REQUEST_URI'], $m))
			{
				$ord = ($m[1] == $k && $m[2] == 'asc') ? 'desc' : 'asc';
			}
			$ordby = str_replace('order_replace_by', 'order_by_'.$k.'_'.$ord, $prm);
		?>
		<th>
			<div class="cell" title="<?= $head ?>">
				<a href="<?= $url.$ordby ?>"><?= $head ?><?= ($ord == 'asc') ? '▼' : '▲' ?></a>
			</div>
		</th>
		<?php
		endforeach
		?>
	</tr>
	</thead>
	<tbody>
	<tr class="insrow">
		<td colspan="<?= $columns + 1 ?>">
			<div>
				<?= $form ?>
			</div>
		</td>
	</tr>
		<?php
		$i = $per_page * ($page - 1) + 1;
		foreach ($list as $row):
		?>
	<tr>
		<td><a class="editbtn" href="javascript:void(0)" id="edit_<?= $row['id'] ?>"><?= $i++ ?></a></td>
		<?php
			$columns2 = 0;
			foreach ($cols as $k => $v):
				$columns2++;
				if (isset($v['original'])) $k = $v['original'];

				$conv_k = 'default';
				if (isset($v['converter']) && isset($converter[$v['converter']])) $conv_k = $v['converter'];
				elseif (isset($converter[$k])) $conv_k = $k;
		?>
		<td><?= isset($converter[$conv_k]) ? $converter[$conv_k]($row, $k) : '' ?></td>
		<?php
				if ($columns == $columns2):
		?>
	</tr>
	<tr class="editrow">
		<td colspan="<?= $columns + 1 ?>">
			<div>
			</div>
		</td>
	</tr>
		<?php
				endif;
			endforeach;
		endforeach
		?>
	</tbody>
</table>
<script>
	$(function(){
		$.ajaxSetup({
			type: 'POST',
			cache: false,
			dataType: 'html'
		});
		$('.insbtn').click(function(){
			$('.insrow').toggle('slow');
			$('#form_0 input:first')[0].focus();
		});
		var setupInsertForm = function(){
			$('#form_0').submit(function(){
				var post_data = {}, data = $(this).serializeArray();
				for (var n in data) post_data[data[n].name] = data[n].value;
				$(this).parent().load(
					$(this).attr('action') + ' div#db_contents',
					post_data,
					function(response){
						if (response == 'ok') {
							location.reload();
						} else {
							setupInsertForm();
							$('html,body').animate({
								scrollTop: $('.insbtn').offset().top
							}, 500);
						}
					}
				);
				return false;
			});
		};
		setupInsertForm();
		$('.editbtn').click(function(){
			var row = $(this).parent().parent().next();
			if (row.css('display') == 'none') {
				var id = this.id.replace(/^edit_/, '');
				var setupUpdateForm = function(){
					$('#form_' + id).submit(function(){
						var post_data = {}, data = $(this).serializeArray();
						for (var n in data) post_data[data[n].name] = data[n].value;
						$(this).parent().load(
							$(this).attr('action') + ' div#db_contents',
							post_data,
							function(response){
								if (response == 'ok') {
									location.reload();
								} else {
									setupUpdateForm();
									$('html,body').animate({
										scrollTop: row.prev().offset().top
									}, 500);
								}
							}
						);
						return false;
					});
					$('#form_' + id + ' input:first')[0].focus();
				};
				row.find('td div').load(
					'<?= $url ?>/read/' + id + ' div#db_contents',
					setupUpdateForm
				);
			}
			row.toggle('slow');
		});
		$('#expander').bind("dragstart", function(event){
		}).bind("drag", function(event){
			$('table.table').css('width', event.originalEvent.x + 'px');
		}).bind("dragend", function(event){
			$(this).attr('title', event.originalEvent.x);
		}).click(function(){
			var w = parseInt(prompt('Input table width'));
			if (w > 0) $('table.table').css('width', w + 'px');
		});
		$(window).bind("dragover", function(event){
			event.preventDefault && event.preventDefault();
		}).bind("dragenter", function(event){
			event.preventDefault && event.preventDefault();
		});
		$('#searchbox').keydown(function(event) {
			switch (event.keyCode) {
				case 13:
					if (event.ctrlKey) {
						var url = '<?= $url ?>/list/' + this.value.replace(/\n/, '').replace(/^\//, '');
						$('#searchform').attr('action', url).trigger('submit');
					}
					break;
				default:
			}
		});

	})
</script>
</div>