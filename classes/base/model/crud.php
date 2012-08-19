<?php

class Base_Model_Crud extends \Orm\Model
{
	/**
	 * テーブルの各カラムの情報
	 *
	 * @var array
	 */
	protected static $_properties = array();

	/**
	 * displayメソッドで生成されるテーブルに利用するカラムの一覧と情報
	 * キーはカラム名、値は下記のような連想配列。
	 * array('label' => '表示名', 'original' => '参照するカラム名', 'converter' =>'コンバーターで利用するキー名'),
	 *
	 * @var array
	 */
	public static $display_columns = array();

	/**
	 * 1ページあたりの表示件数
	 *
	 * @var int
	 */
	public static $per_page = 10;

	/**
	 * displayメソッドで生成されるHTMLのテンプレート
	 *
	 * @var array
	 */
	public static $display_template = array(
		'list' => 'list/list',
		'read' => 'read/read',
	);

	/**
	 * displayメソッドで生成されるページで必要とするbootstrapをcdnから読み込むか
	 *
	 * @var bool
	 */
	public static $display_bootstrap = true;

	/**
	 * displayメソッドで生成されるページで必要とするbootstrapをcdnから読み込むか
	 *
	 * @var bool
	 */
	public static $display_jquery = true;

	/**
	 * モデルから生成したインスタンスのキャッシュ
	 *
	 * @var null
	 */
	public static $display_model_cached = null;

	/**
	 * 取得した辞書のキャッシュ用連想配列
	 *
	 * @var array
	 */
	public static $dictionary_cached = array();

	/**
	 * モデルの元となるテーブルを一覧、更新、削除するためのHTMLを生成
	 *
	 * @static
	 * @return Fuel\Core\View|string
	 */
	public static function display()
	{
		// 呼び出し元ページのURLセグメントから必要な情報を取得
		$model = strtolower(str_replace('Model_', '', get_called_class()));
		$segs = array_reverse(\Fuel\Core\Request::active()->route->segments);
		$prms = array();
		foreach ($segs as &$v)
		{
			if ($model == $v) break;
			array_unshift($prms, $v);
			$v = null;
		}
		$mode = (count($prms) > 0) ? array_shift($prms) : 'list';
		if ($mode != 'list') $id = (count($prms) > 0) ? array_shift($prms) : 0;
		$url = '/'.rtrim(join('/', array_reverse($segs)), '/');

		switch ($mode)
		{
			case 'delete':
				if (static::displayDelete($id))
				{
					\Response::redirect(
						(strpos($_SERVER['HTTP_REFERER'], $url.'/read') !== false)
						? $url.'/list'
						: $_SERVER['HTTP_REFERER']
					);
				}
				break;
			case 'insert':
			case 'update':
				static::loadFormData();
				if (static::displaySave($id))
				{
					$referer = preg_replace("%($url)%", "$1/list", $_SERVER['HTTP_REFERER']);
					if (strpos($referer, $url.'/list') !== false) die('ok');
					\Response::redirect($url.'/read/'.$id);
				}
				$html = static::displayRead($url, $id);
			break;
			case 'read':
				static::loadFormData();
				$html = static::displayRead($url, $id);
			break;
			case 'list':
				static::loadFormData();
				$form = static::displayRead($url);
				\Fuel\Core\View::set_global('form', sprintf('%s', $form), false);
				$html = static::displayList($url, $prms);
			break;
			default:
		}

		return $html;
	}

	/**
	 * displayメソッド用の削除処理
	 *
	 * @param int $id 主キー
	 * @return bool
	 */
	protected static function displayDelete($id)
	{
		static::$display_model_cached = static::find($id);
		if (static::$display_model_cached && static::$display_model_cached->delete())
		{
			\Fuel\Core\Session::set_flash('result', 'Complete data delete:'.$id);
			return true;
		}
		else
		{
			\Fuel\Core\Session::set_flash('result', 'Failure data delete:'.$id);
			return false;
		}
	}

	/**
	 * displayメソッド用の挿入、更新処理
	 *
	 * @static
	 * @param int $id 主キー
	 * @return bool
	 */
	protected static function displaySave($id = 0)
	{
		try
		{
			static::saveModel($id);
			\Fuel\Core\Session::set_flash('result', 'Complete data update:'.$id);
			return true;
		}
		catch (\Orm\ValidationFailed $e)
		{
			\Fuel\Core\View::set_global('error', $e->getMessage(), false);
			return false;
		}
	}

	/**
	 * 挿入、更新処理を実行
	 *
	 * @static
	 * @param int $id 主キー
	 * @throws Orm\ValidationFailed
	 */
	public static function saveModel($id = 0)
	{
		static::$display_model_cached = ($id > 0) ? static::find($id) : static::forge();
		foreach (static::$_properties as $k => $v)
		{
			if (is_array($v)) $v = $k;
			if (Input::param($v, false) !== false)
			{
				static::$display_model_cached->$v = \Fuel\Core\Input::param($v, null);
			}
		}
		try
		{
			static::$display_model_cached->save();
		}
		catch (\Orm\ValidationFailed $e)
		{
			throw $e;
		}
	}

	/**
	 * displayメソッド用の挿入、更新用フォームHTML生成
	 *
	 * @static
	 * @param $url string displayメソッドを呼び出すコントローラ、アクションのURL
	 * @param int $id 主キー
	 * @return string フォームのHTML
	 */
	protected static function displayRead($url, $id = 0)
	{
		// フォーム生成
		$fieldset = \Fuel\Core\Fieldset::forge();
		$fieldset->set_config(array('form_attributes' => array('id' => 'form_'.$id)));
		if ($id > 0)
		{
			$func = "if (confirm('Really?')) location.href='{$url}/delete/{$id}'";
			$url .= '/update/'.$id;
			if (!static::$display_model_cached) static::$display_model_cached = static::find($id);
			$fieldset->add_model(static::$display_model_cached);
			$fieldset->add('update'.$id, '', array('type'=>'submit', 'value'=>'update'));
			$fieldset->add('delete'.$id, '', array('type'=>'button', 'value'=>'delete', 'onclick' => $func));
		}
		else
		{
			$url .= '/insert';
			if (!static::$display_model_cached) static::$display_model_cached = static::forge();
			$fieldset->add_model(static::$display_model_cached);
			$fieldset->add('insert', '', array('type'=>'submit', 'value'=>'insert'));
		}
		$fieldset->populate(static::$display_model_cached, true);
		\Fuel\Core\View::set_global('form', $fieldset->build($url), false);
		return \Fuel\Core\View::forge(static::$display_template['read'], array('id' => $id));
	}

	/**
	 * displayメソッド用の一覧HTML生成
	 *
	 * @static
	 * @param string $url displayメソッドを呼び出すコントローラ、アクションのURL
	 * @param array $prms 絞り込み条件 フィールド名、演算子、値を1セットとして繰り返した分だけAND検索を行う。並び順や1ページあたりの表示数も指定可能
	 * @return Fuel\Core\View
	 */
	protected static function displayList($url, $prms)
	{
		$option = $prms2 = array();
		$per_page = static::$per_page;
		$i = 0;

		// 絞り込み条件にpが含まれてなければ入れておく。条件文がややっこしいのは要素数が1の時に最後の条件文を実行させないようにするため
		if (!count($prms) || !($prms[count($prms) - 1] == 'p' || $prms[count($prms) - 2] == 'p')) $prms[] = 'p';

		foreach ($prms as $k => $v)
		{
			// 並び順
			if (preg_match('/^(?:order_?by_)?(.+)_(asc|desc)$/', $v, $m))
			{
				$option['order_by'][] = array($m[1], $m[2]);
			}
			// 1ページあたりの表示数
			elseif (preg_match('/^per_page_(\d+)/', $v, $m))
			{
				$per_page = intval($m[1]);
			}
			// ページ数にいきあたったらループ終了
			elseif ($v == 'p' && count($prms) <= $k + 2)
			{
				$prms2[] = 'order_replace_by';
				$prms2[] = $v;
				if (isset($prms[$k + 1])) $prms2[] = $prms[$k + 1];
				break;
			}
			// 条件文を作成
			else
			{
				if (($k + 1) % 3) $option['where'][$i][] = html_entity_decode($v);
				else $option['where'][$i++][] = html_entity_decode($v);

				$prms2[] = $v;
			}
		}
		// 並び順が指定されていなければデフォルトの並び順を設定
		if (!isset($option['order_by']))
		{
			$option['order_by'] = array(array('id', 'asc'));
		}

		$total = static::count($option);
		list($option['limit'], $option['offset'], $page) = static::displayPaginate($url, $total, $per_page);
		$list = static::find('all', $option);

		// 検索窓から検索してきた場合は検索ワードをセッションに保存してページ繰りしても維持するようにする
		$word = \Fuel\Core\Session::get('crud_display_search_word', '');
		if (\Fuel\Core\Input::method() == 'POST')
		{
			$word = \Fuel\Core\Input::post('word', '');
			\Fuel\Core\Session::set('crud_display_search_word', $word);
		}

		\Fuel\Core\View::set_global('pager', \Fuel\Core\Pagination::create_links(), false);
		$dat  = array(
			'list' => $list,
			'prop' => static::$_properties,
			'cols' => (count(static::$display_columns)) ? static::$display_columns : static::$_properties,
			'url' => $url,
			'prm' => '/list/'.join('/', $prms2),
			'total' => $total,
			'page' => $page,
			'per_page' => $per_page,
			'search_word' => $word,
			'converter' => static::getColumnConverter(),
			'bootstrap_cdn' => static::$display_bootstrap,
			'jquery_cdn' => static::$display_jquery
		);
		return \Fuel\Core\View::forge(static::$display_template['list'], $dat);
	}

	/**
	 * displayメソッド用のページネーション設定
	 *
	 * @static
	 * @param $url string displayメソッドを呼び出すコントローラ、アクションのURL
	 * @param $total int 総数
	 * @param $num int 1ページあたりの表示数
	 * @return array
	 */
	protected static function displayPaginate($url, $total, $num = 10)
	{
		$url .= '/list';
		$seg_num = count(explode('/', $url)) + 1;
		$segs = \Fuel\Core\Request::active()->route->segments;
		if ($url != '/'.rtrim(join('/', $segs), '/list').'/list')
		{
			if ($segs[count($segs) - 2] == 'p')
			{
				array_splice($segs, -2);
			}
			elseif ($segs[count($segs) - 1] == 'p')
			{
				array_splice($segs, -1);
			}
			$url = join('/', $segs);
			$seg_num = count($segs) + 2;
		}
		$config = array(
			'pagination_url' => $url.'/p',
			'total_items' => $total,
			'per_page' => $num,
			'uri_segment' => $seg_num,
			'num_links' => 3,
			'template' => array(
				'wrapper_start'=>'<ul>',
				'wrapper_end'=>'</ul>',
				'previous_start'=>'<li class="previous">',
				'previous_end'=>'<li>',
				'previous_inactive_start'=>'<li class="active"><a href="#">',
				'previous_inactive_end'=>'</a></li>',
				'next_inactive_start'=>'<span class="active"><a href="#">',
				'next_inactive_end'=>'</a></span>',
				'next_start'=>'<li class="next">',
				'next_end'=>'</li></ul>',
				'active_start'=>'<span class="active"><a href="#">',
				'active_end'=>'</a></span>',
			),
		);
		\Fuel\Core\Pagination::set_config($config);
		return array(\Fuel\Core\Pagination::$per_page, \Fuel\Core\Pagination::$offset, \Fuel\Core\Pagination::$current_page);
	}

	/**
	 * displayメソッド用に生成するテーブルHTMLの各データの表示処理を格納する配列を取得
	 *
	 * @static
	 * @return array
	 */
	public static function getColumnConverter()
	{
		$converter = array(
			/**
			 * displayメソッド用に生成するテーブルHTMLの各データの表示部分を作成
			 *
			 * @static
			 * @param array $data 現在処理している行のデータ
			 * @param string $column 現在処理している行の表示しようとしている列名
			 * @return string
			 */
			'default' => function($data, $column) {
				return sprintf('<div class="cell" title="%s">%s</div>', $data[$column], $data[$column]);
			}
		);
		return $converter;
	}

	/**
	 * 必要に応じてフォームにテキストボックス以外のフォームを設置する
	 *
	 * フォームを設置するにはstatic::$_properties.column.form.typeでフォームの種類を指定し、
	 * static::$_properties.column.form.optionsでvalueとラベルの連想配列を指定する
	 * 連想配列で必要なデータはstatic::$_properties.column.form_option_loaderにて次のように設定して取得する
	 * static::$_properties.column.form_option_loader = array('Model_XXX', 'YYY', args, args..);
	 * call_user_func_arrayで最初の2つの値でメソッド名を指定し、残りの値を引数としてメソッドを呼び出す
	 *
	 * @static
	 *
	 */
	public static function loadFormData()
	{
		foreach (static::$_properties as $k => &$v)
		{
			if (!is_array($v)) continue;
			if (isset($v['form']) && isset($v['form']['type']) && isset($v['form_option_loader']))
			{
				switch ($v['form']['type'])
				{
					case 'select':
					case 'radio':
					case 'checkbox':
						$caller = array_splice($v['form_option_loader'], 0, 2);
						if (method_exists($caller[0], $caller[1]))
						{
							$v['form']['options'] = call_user_func_array($caller, $v['form_option_loader']);
						}
					break;
				}
			}
		}
	}

	/**
	 * 指定のカラムを値とする連想配列の辞書を取得する。キーはデフォルトでは主キー
	 *
	 * @static
	 * @param string $value 値とするカラム
	 * @param string|null $key 主キー以外をキーとしたい場合にカラムを指定
	 * @param array 検索条件
	 * @return mixed
	 */
	public static function getDictionary($value, $key = null, $option = array())
	{
		if ($key == null) $key = static::primary_key();
		if (is_array($key)) $key = join('+', $key);
		if (!isset(static::$dictionary_cached[$value.':'.$key]))
		{
			static::$dictionary_cached[$value.':'.$key] = array();
			foreach (static::find('all', $option) as $dat)
			{
				static::$dictionary_cached[$value.':'.$key][$dat[$key]] = $dat[$value];
			}
		}
		return static::$dictionary_cached[$value.':'.$key];
	}
}