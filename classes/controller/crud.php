<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Crud extends Controller {

    public $belongs_to = array();
    public $has_many = array();
    public $columns = array();
    public $model;
    public $rows;
    public $has_many_names = array();
    public $title;
    public $model_array = array();

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request,$response);

        $models = $this->array_keys_r(Kohana::list_files('classes/model'));
        foreach($models as $model) {
            if (strpos($model, '.php') !== false) {
                $model = str_replace('.php', '', $model);
                $model = str_replace('classes/model/', '', $model);
                $this->model_array[] = str_replace('/', '_', $model);
            }
        }
    }

    public function action_index()
    {
        $view = new View_Crud_Index();
        $view->models = $this->model_array;
        $this->response->body($view);
    }

    public function action_read()
    {
        $this->factory();
        if($this->model->loaded())
        {
            $view = new View_Crud_Read_Single();
        }
        else
        {
            $view = new View_Crud_Read_Multiple();
        }
        $view->columns = $this->columns;
        if($this->rows) {
            $view->rows = array_values($this->rows);
        }
        if (!$this->request->is_initial()) // Is internal request
        {
            $view->render_layout = FALSE;
        }
        $view->model = $this->model->object_name();
        $view->pk = $this->request->param('pk');
        $view->model_pretty = ucwords(str_replace('_', ' ', $this->model->object_name()));
        $view->model_plural = ucwords($this->title);
        $view->has_many = $this->has_many;
        $this->response->body($view);
    }

    public function action_create()
    {
        $this->factory();
        $view = new View_Crud_Create();
        $view->columns = $this->columns;
        $view->model = $this->request->param('id');
        $view->model_pretty = ucwords($this->request->param('id'));
        $view->model_plural = Inflector::plural(ucwords($this->request->param('id')));
        $view->referrer = $this->request->referrer();
        $this->response->body($view);
    }

    public function action_update()
    {
        $this->factory();
        $view = new View_Crud_Update();
        $view->columns = $this->columns;
        $view->pk = $this->request->param('pk');
        $view->model = $this->request->param('id');
        $view->referrer = $this->request->referrer();
        $view->model_pretty = ucwords($this->request->param('id'));
        $view->model_plural = Inflector::plural(ucwords($this->request->param('id')));
        $this->response->body($view);
    }

    public function action_delete()
    {
        $this->factory();
        $this->model->delete();
        $this->request->redirect($this->request->referrer());
    }

    public function build_rows($value)
    {

        foreach (array_keys($this->model->object()) as $column) {
            if($column == $value->primary_key()) {
                $this->rows[$value->pk()][] = '<a href="/crud/read/'.$value->object_name().'/'.$value->$column.'"  class="btn btn-mini">View</a>';
                $action = '<a href="/crud/update/'.$value->object_name().'/'.$value->$column.'"  class="btn btn-mini btn-primary">Edit</a> <a href="/crud/delete/'.$value->object_name().'/'.$value->$column.'" class="btn btn-danger btn-mini">Delete</a>';
            } else {
                if(in_array($column, $this->belongs_to)) {
                    $column = array_search($column, $this->belongs_to);
                    $this->rows[$value->pk()][] = '<a href="/crud/read/'.$column.'/'.$value->$column->pk().'">'.$value->$column->name.'</a>';
                } else {
                    $this->rows[$value->pk()][] = $value->$column;
                }
            }
        }
        $this->rows[$value->pk()][] = $action;

        return $this;
    }

    public function factory()
    {

        $model = 'Model_'.$this->request->param('id');
        if($this->request->post('data')['id']) {
            if($this->request->post('model')) {
                $save_model = 'Model_'.$this->request->post('model');
                $this->model = new $save_model($this->request->post('data')['id']);
            } else {
                $this->model = new $model($this->request->post('data')['id']);
            }
        } else {
            if($this->request->param('submodel')) {
                $submodel = 'Model_'.$this->request->param('submodel');
                $this->model = new $submodel();
                $relationship = $this->request->param('id').'_id';
                if(array_key_exists($relationship, $this->model->table_columns())) {
                    $this->model->where($relationship,'=',$this->request->param('pk'));
                }
            } else {
                $this->model = new $model($this->request->param('pk'));
                $this->title = $this->model->object_plural();
            }

        }

        if($this->request->post('data')) {
            foreach ($this->request->post('data') as $key=>$value){
                $this->model->$key = $value;
            }
            $this->model->save();
            $this->request->redirect($this->request->uri());
        }

        foreach($this->model->belongs_to() as $value)
        {
            $this->belongs_to[$value['model']] = $value['foreign_key'];
        }

        foreach($this->model->has_many() as $value)
        {
            if($this->request->param('pk')) {
                $parent = new $model($this->request->param('pk'));
                foreach($parent->has_many() as $key=>$many) {
                    $this->has_many_names[$key] = $many['model'];
                }
                $title = array_search($value['model'], $this->has_many_names);
                $this->has_many[] = array('title'=>ucwords($title), 'data'=>Request::factory('/crud/read/'.$this->request->param('id').'/'.$this->request->param('pk').'/'.$value['model'])->execute()->body());
            } else {
                $this->has_many[] = Request::factory('/crud/read/'.$value['model'])->execute()->body();
            }
        }

        if($this->model->loaded())
        {
            $this->build_rows($this->model);
            foreach ($this->model->has_many() as $value)
            {
                $this->has_many[] = '';
            }
        }
        else
        {
            foreach ($this->model->find_all() as $value)
            {
                $this->build_rows($value);
            }
        }
        foreach(array_keys($this->model->object()) as $column)
        {
            if($column != $this->model->primary_key())
            {
                if(in_array($column, $this->belongs_to))
                {
                    $column_new = array_search($column, $this->belongs_to);

                    $form_model = ORM::factory($column_new)
                        ->find_all()->as_array('id', 'name');

                    $field = Form::select('data['.$column.']', $form_model, $this->model->$column);

                    $column = $column_new;

                    $this->columns[] = array('column'=>ucwords($column),'field'=>$field, 'value' =>  '<a href="/crud/read/'.$column.'/'.$this->model->$column->pk().'">'.$this->model->$column->name.'</a>');

                } else {
                    $field = Form::input('data['.$column.']', $this->model->$column);
                    $this->columns[] = array('column'=>ucwords($column),'field'=>$field, 'value' => $this->model->$column);
                }

            } else {

                if($this->model->$column) {
                    $field = $this->model->$column.Form::hidden('data['.$column.']', $this->model->$column);
                } else {
                    $field = 'Auto Generated'.Form::hidden('data['.$column.']', 'NULL');
                }
                $action = '<a href="/crud/update/'.$this->request->param('id').'/'.$this->model->$column.'"  class="btn btn-mini btn-primary">Edit</a> <a href="/crud/delete/'.$this->request->param('id').'/'.$this->model->$column.'" class="btn btn-danger btn-mini">Delete</a>';
                $this->columns[] = array('column'=>ucwords($column),'field'=>$field, 'value' => $this->model->$column);
            }
        }
        $this->columns[] = array('column'=>'Actions', 'value' => $action);

        return $this;

    }

    public function array_keys_r($array) {
        $keys = array_keys($array);

        foreach ($array as $i)
            if (is_array($i))
                $keys = array_merge($keys, $this->array_keys_r($i));

        return $keys;
    }

    public function action_media()
    {
        // Get the file path from the request
        $file = $this->request->param('file');

        // Find the file extension
        $ext = pathinfo($file, PATHINFO_EXTENSION);

        // Remove the extension from the filename
        $file = substr($file, 0, -(strlen($ext) + 1));

        if ($file = Kohana::find_file('media/crud', $file, $ext))
        {
            // Check if the browser sent an "if-none-match: <etag>" header, and tell if the file hasn't changed
            $this->response->check_cache(sha1($this->request->uri()).filemtime($file), $this->request);

            // Send the file content as the response
            $this->response->body(file_get_contents($file));

            // Set the proper headers to allow caching
            $this->response->headers('content-type',  File::mime_by_ext($ext));
            $this->response->headers('last-modified', date('r', filemtime($file)));
        }
        else
        {
            // Return a 404 status
            $this->response->status(404);
        }
    }

} // End Welcome
