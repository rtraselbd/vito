<?php

namespace App\Http\Controllers\API;

use App\Actions\Projects\CreateProject;
use App\Actions\Projects\DeleteProject;
use App\Actions\Projects\UpdateProject;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

#[Group(name: 'projects')]
class ProjectController extends Controller
{
    #[Endpoint(title: 'list', description: 'Get all projects.')]
    #[ResponseFromApiResource(ProjectResource::class, Project::class, collection: true, paginate: 25)]
    public function index(): ResourceCollection
    {
        $this->authorize('viewAny', Project::class);

        return ProjectResource::collection(Project::all());
    }

    #[Endpoint(title: 'create', description: 'Create a new project.')]
    #[BodyParam(name: 'name', description: 'The name of the project.', required: true)]
    #[ResponseFromApiResource(ProjectResource::class, Project::class)]
    public function store(Request $request): ProjectResource
    {
        $this->authorize('create', Project::class);

        $this->validate($request, CreateProject::rules());

        $project = app(CreateProject::class)->create(auth()->user(), $request->all());

        return new ProjectResource($project);
    }

    #[Endpoint(title: 'show', description: 'Get a project by ID.')]
    #[ResponseFromApiResource(ProjectResource::class, Project::class)]
    public function show(Project $project): ProjectResource
    {
        $this->authorize('view', $project);

        return new ProjectResource($project);
    }

    #[Endpoint(title: 'update', description: 'Update project.')]
    #[BodyParam(name: 'name', description: 'The name of the project.', required: true)]
    #[ResponseFromApiResource(ProjectResource::class, Project::class)]
    public function update(Request $request, Project $project): ProjectResource
    {
        $this->authorize('update', $project);

        $this->validate($request, UpdateProject::rules($project));

        $project = app(UpdateProject::class)->update($project, $request->all());

        return new ProjectResource($project);
    }

    #[Endpoint(title: 'delete', description: 'Delete project.')]
    #[\Knuckles\Scribe\Attributes\Response(status: 204)]
    public function delete(Project $project): Response
    {
        $this->authorize('delete', $project);

        app(DeleteProject::class)->delete(auth()->user(), $project);

        return response()->noContent();
    }
}
