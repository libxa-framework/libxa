<?php

declare(strict_types=1);

namespace Libxa\Nova\Http\Controllers;

use Libxa\Http\Request;
use Libxa\Http\Response;
use Libxa\Nova\ResourceManager;
use Libxa\Atlas\DB;

class NovaController
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected ResourceManager $resources)
    {
    }

    /**
     * Display the dashboard.
     */
    public function dashboard(Request $request): Response
    {
        return view('nova.dashboard', [
            'resources' => $this->resources->all(),
        ]);
    }

    /**
     * Display the index for a resource.
     */
    public function index(string $resourceKey, Request $request): Response
    {
        $resource = $this->resources->get($resourceKey);

        if (! $resource) {
            abort(404);
        }

        /** @var \Libxa\Atlas\Model $modelClass */
        $modelClass = $resource::$model;
        $models     = $modelClass::all();

        return view('nova.index', [
            'resource' => $resource,
            'models'   => $models,
            'title'    => $resource::label(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(string $resourceKey, Request $request): Response
    {
        $resource = $this->resources->get($resourceKey);

        if (! $resource) {
            abort(404);
        }

        return view('nova.form', [
            'resource' => $resource,
            'model'    => null, // Creating new
            'title'    => 'Create ' . $resource::label(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(string $resourceKey, Request $request): Response
    {
        $resource = $this->resources->get($resourceKey);
        $modelClass = $resource::$model;

        $data = $request->all();
        // Simple storage logic for now
        $modelClass::create($data);

        return redirect("/admin/resources/{$resourceKey}");
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $resourceKey, string $id, Request $request): Response
    {
        $resource = $this->resources->get($resourceKey);
        $modelClass = $resource::$model;

        $model = $modelClass::find((int) $id);

        if (! $resource || ! $model) {
            abort(404);
        }

        return view('nova.form', [
            'resource' => $resource,
            'model'    => $model,
            'title'    => 'Edit ' . $resource::label(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(string $resourceKey, string $id, Request $request): Response
    {
        $resource = $this->resources->get($resourceKey);
        $modelClass = $resource::$model;

        $model = $modelClass::find((int) $id);
        
        if ($model) {
            $model->update($request->all());
        }

        return redirect("/admin/resources/{$resourceKey}");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $resourceKey, string $id, Request $request): Response
    {
        $resource = $this->resources->get($resourceKey);
        $modelClass = $resource::$model;

        $model = $modelClass::find((int) $id);
        
        if ($model) {
            $model->delete();
        }

        return redirect("/admin/resources/{$resourceKey}");
    }
}
