<?php

namespace Ehyiah\ApiDocBundle\Enum;

enum ComponentType: string
{
    case Schemas = 'schemas';
    case RequestBodies = 'requestBodies';
    case Parameters = 'parameters';
    case Headers = 'headers';
    case Responses = 'responses';
    case SecuritySchemes = 'securitySchemes';
    case Examples = 'examples';
    case Routes = 'routes';
}
