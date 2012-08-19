basemodel
==============

モデルに汎用メソッドを追加したパッケージ。
モデルから\Base_Model_Crudを継承して使う。

```php
// 一覧、ソート、検索、挿入、更新、削除を行うためのHTMLを取得する
Model_Yours::display();

// FieldSetでテキストエリア以外のフォームを設置したい時に、必要なデータを取得する
Model_Yours::loadFormData()

// 主キーと指定カラムの値による辞書を連想配列で取得する
Model_Yours::getDictionary($value_column);

// 指定カラムのキーと指定カラムの値による辞書を連想配列で取得する
Model_Yours::getDictionary($value_column, $key_column);

// findメソッドと同じようにオプションを指定して結果を絞り込んだ辞書を連想配列で取得する
Model_Yours::getDictionary($value_column, $key_column, $option);
```

## displayの機能拡張
- static::$display_columns
 - HTMLの一覧に表示したいカラムやそのカラムに必要な情報を設定する
- static::getColumnConverter()
 - 一覧の各カラムの表示方法を設定する
- static::loadFormData()
 - 入力フォームにセレクトボックスなどを指定した時に利用するデータを設定する
- static::$_properties.column.form_option_loader
 - セレクトボックスなどで必要とするデータの取得方法を設定する
