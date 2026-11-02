<?php
namespace App\Controllers;

class TestController extends BaseController
{
    public function preview()
    {
        //  datos de prueba si la vista los necesita
        $data = [
            'title' => 'Vista previa de la pantalla Stitch'
        ];

        return view('admin/users/create', $data);
    }
}