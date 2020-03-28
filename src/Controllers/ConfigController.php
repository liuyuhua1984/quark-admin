<?php

namespace QuarkCMS\QuarkAdmin\Controllers;

use Illuminate\Http\Request;
use QuarkCMS\QuarkAdmin\Models\Config;
use QuarkCMS\QuarkAdmin\Helper;
use DB;
use Cache;
use Str;
use Quark;
use Validator;

class ConfigController extends QuarkController
{
    public $title = '配置';

    /**
     * Form页面模板
     * 
     * @param  Request  $request
     * @return Response
     */
    public function websiteForm()
    {
        $groupNames = Config::where('status', 1)
        ->select('group_name')
        ->distinct()
        ->get();

        $form = Quark::form()->setAction('admin/config/saveWebsite');

        $form->tab('Basic info', function ($form) {
            $form->text('title','标题');
        })->tab('Profile', function ($form) {
            $form->text('name','名称');
            $form->text('group_name','分组名称');
            $form->textArea('remark','备注');
        });

        return $form;
    }

    /**
     * 网站设置
     *
     * @param  Request  $request
     * @return Response
     */
     public function website(Request $request)
     {
        $form = $this->websiteForm();

        $content = Quark::content()
        ->title($this->title())
        ->body(['form'=>$form->render()]);

        return $this->success('获取成功！','',$content);
    }

    /**
    * 保存站点配置数据
    *
    * @param  Request  $request
    * @return Response
    */
    public function saveWebsite(Request $request)
    {

        $requestJson    =   $request->getContent();
        $requestData    =   json_decode($requestJson,true);


        $envPath = base_path() . DIRECTORY_SEPARATOR . '.env';

        if(!is_writable($envPath)) {
            return $this->error('操作失败，请检查.env文件是否具有写入权限');
        }

        $result = true;
        // 遍历插入数据
        foreach ($requestData as $key => $value) {
            // 修改时清空缓存
            Cache::pull($key);

            $config = Config::where('name',$key)->first();

            if(($config['type'] == 'file') || ($config['type'] == 'picture')) {
                if($value) {
                    $value = $value[0]['id'];
                } else {
                    $value = null;
                }
            }

            if($config['name'] == 'APP_DEBUG') {

                if($value) {
                    $data = [
                        'APP_DEBUG' => 'true'
                    ];
                } else {
                    $data = [
                        'APP_DEBUG' => 'false'
                    ];
                }

                Helper::modifyEnv($data);
            }

            $getResult = Config::where('name',$key)->update(['value'=>$value]);
            if($getResult === false) {
                $result = false;
            }
        }

        if ($result) {
            return $this->success('操作成功！','');
        } else {
            return $this->error('操作失败！');
        }
    }

    /**
     * 列表页面
     *
     * @param  Request  $request
     * @return Response
     */
    protected function table()
    {
        $grid = Quark::grid(new Config)
        ->title($this->title);

        $grid->column('title','标题')->link();
        $grid->column('name','名称');
        $grid->column('remark','备注');
        $grid->column('status','状态')->editable('switch',[
            'on'  => ['value' => 1, 'text' => '正常'],
            'off' => ['value' => 2, 'text' => '禁用']
        ])->width(100);
        $grid->column('actions','操作')->width(100)->rowActions(function($rowAction) {
            $rowAction->menu('edit', '编辑');
            $rowAction->menu('delete', '删除')->model(function($model) {
                $model->delete();
            })->withConfirm('确认要删除吗？','删除后数据将无法恢复，请谨慎操作！');
        });

        // 头部操作
        $grid->actions(function($action) {
            $action->button('create', '新增');
            $action->button('refresh', '刷新');
        });

        // select样式的批量操作
        $grid->batchActions(function($batch) {
            $batch->option('', '批量操作');
            $batch->option('resume', '启用')->model(function($model) {
                $model->update(['status'=>1]);
            });
            $batch->option('forbid', '禁用')->model(function($model) {
                $model->update(['status'=>2]);
            });
            $batch->option('delete', '删除')->model(function($model) {
                $model->delete();
            })->withConfirm('确认要删除吗？','删除后数据将无法恢复，请谨慎操作！');
        })->style('select',['width'=>120]);

        $grid->search(function($search) {
            $search->where('title', '搜索内容',function ($query) {
                $query->where('title', 'like', "%{input}%");
            })->placeholder('标题');
        })->expand(false);

        $grid->model()->paginate(10);

        return $grid;
    }

    /**
     * 表单页面
     * 
     * @param  Request  $request
     * @return Response
     */
    protected function form()
    {
        $id = request('id');

        $form = Quark::form(new Config);

        $title = $form->isCreating() ? '创建'.$this->title : '编辑'.$this->title;
        $form->title($title);
        
        $form->id('id','ID');

        $form->text('title','标题')
        ->rules(['required','max:20'],['required'=>'标题必须填写','max'=>'标题不能超过20个字符']);

        $options = [
            'text'=>'输入框',
            'textarea'=>'文本域',
            'picture'=>'图片',
            'file'=>'文件',
            'switch'=>'开关'
        ];

        $form->select('type','表单类型')
        ->options($options)
        ->default('text')
        ->width(200);

        $form->text('name','名称')
        ->rules(['required','max:255'],['required'=>'名称必须填写','max'=>'名称不能超过255个字符'])
        ->creationRules(["unique:configs"],['unique'=>'名称已经存在'])
        ->updateRules(["unique:configs,name,{{id}}"],['unique'=>'名称已经存在']);

        $form->text('group_name','分组名称');

        $form->textArea('remark','备注')
        ->rules(['max:255'],['max'=>'备注不能超过255个字符']);

        $form->switch('status','状态')->options([
            'on'  => '正常',
            'off' => '禁用'
        ])->default(true);

        return $form;
    }
}
