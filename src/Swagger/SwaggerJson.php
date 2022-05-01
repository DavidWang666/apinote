<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace ChenGang\ApiNote\Swagger;

use ChenGang\ApiNote\Annotation\ApiController;
use ChenGang\ApiNote\Annotation\ApiDefinition;
use ChenGang\ApiNote\Annotation\ApiDefinitions;
use ChenGang\ApiNote\Annotation\ApiResponse;
use ChenGang\ApiNote\Annotation\ApiServer;
use ChenGang\ApiNote\Annotation\ApiVersion;
use ChenGang\ApiNote\Annotation\Body;
use ChenGang\ApiNote\Annotation\FormData;
use ChenGang\ApiNote\Annotation\GetApi;
use ChenGang\ApiNote\Annotation\Header;
use ChenGang\ApiNote\Annotation\Mapping;
use ChenGang\ApiNote\Annotation\Param;
use ChenGang\ApiNote\Annotation\Query;
use ChenGang\ApiNote\Annotation\Story;
use ChenGang\ApiNote\ApiAnnotation;
use Doctrine\Common\Annotations\AnnotationReader;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Arr;
use Psr\Log\LoggerInterface;

class SwaggerJson
{
    /**
     * @var ConfigInterface|mixed
     */
    public $config;

    public $swagger;

    /**
     * @var LoggerInterface
     */
    public $logger;

    public $server;

    public function __construct($server)
    {
        $container = ApplicationContext::getContainer();
        $this->config = $container->get(ConfigInterface::class);
        $this->logger = $container->get(LoggerFactory::class)->get('apinote');
        $this->swagger = $this->config->get('apinote.swagger');
        $this->server = $server;
    }

    public function addPath($className, $methodName)
    {
        //忽略注解
        $ignores = $this->config->get('annotations.scan.ignore_annotations', []);
        foreach ($ignores as $ignore) {
            AnnotationReader::addGlobalIgnoredName($ignore);
        }
        $classAnnotation = ApiAnnotation::classMetadata($className);
        $controllerAnnotation = $classAnnotation[ApiController::class] ?? null;
        $serverAnnotation = $classAnnotation[ApiServer::class] ?? null;
        $versionAnnotation = $classAnnotation[ApiVersion::class] ?? null;
        $definitionsAnnotation = $classAnnotation[ApiDefinitions::class] ?? null;
        $definitionAnnotation = $classAnnotation[ApiDefinition::class] ?? null;
        $bindServer = $serverAnnotation ? $serverAnnotation->name : $this->config->get('server.servers.0.name');

        $servers = $this->config->get('server.servers');
        $servers_name = array_column($servers, 'name');
        if (!in_array($bindServer, $servers_name)) {
            throw new \Exception(sprintf('The bind ApiServer name [%s] not found, defined in %s!', $bindServer, $className));
        }

        if ($bindServer !== $this->server) {
            return;
        }

        $methodAnnotations = ApiAnnotation::methodMetadata($className, $methodName);

        $headerAnnotation = $classAnnotation[Header::class] ?? null;
        $queryAnnotation = $classAnnotation[Query::class] ?? null;
        if ($headerAnnotation !== null) {
            $methodAnnotations[] = $headerAnnotation;
        }
        if ($queryAnnotation !== null) {
            $methodAnnotations[] = $queryAnnotation;
        }

        if (!$controllerAnnotation || !$methodAnnotations) {
            return;
        }

        $auth = $controllerAnnotation->auth;
        $params = [];
        $responses = [];
        /** @var GetApi $mapping */
        $mapping = null;
        $consumes = null;
        $story = null;
        foreach ($methodAnnotations as $option) {
            if ($option instanceof Mapping) {
                $mapping = $option;
                $auth = is_bool($option->auth) ? $option->auth : $auth;
            }
            if ($option instanceof Param) {
                $params[] = $option;
            }
            if ($option instanceof ApiResponse) {
                $responses[] = $option;
            }
            if ($option instanceof FormData) {
                $consumes = 'application/x-www-form-urlencoded';
            }
            if ($option instanceof Body) {
                $consumes = 'application/json';
            }

            if ($option instanceof Story) {
                $story = $option->value;
            }
        }
        $this->makeDefinition($definitionsAnnotation);
        $definitionAnnotation && $this->makeDefinition([$definitionAnnotation]);

        $tag = $controllerAnnotation->tag ?: $className;
        $this->swagger['tags'][$tag] = [
            'name' => $tag,
            'description' => $controllerAnnotation->description,
        ];

        $basePath = $this->config->get('apinote.route.basePath');
        $path = $mapping->path;
        $prefix = $basePath . '/' . $controllerAnnotation->prefix;
        $tokens = [$versionAnnotation ? $versionAnnotation->version : null, $prefix, $path];
        $tokens = array_map(function ($item) {
            return ltrim($item, '/');
        }, array_filter($tokens));
        $path = '/' . implode('/', $tokens);

        $method = strtolower($mapping->methods[0]);
        $summary = $mapping->summary ?? '';
        if ($auth) {
            $summary .= '(Login)';
        }
        $data = [
            'tags' => [$tag],
            'summary' => $summary,
            'description' => $mapping->description ?? '',
            'operationId' => implode('', array_map('ucfirst', explode('/', $path))) . $mapping->methods[0],
            'parameters' => $this->makeParameters($params, $path, $method),
            'produces' => [
                'application/json',
            ],
            'responses' => $this->makeResponses($responses, $path, $method),
        ];
        if ($story != null) {
            $data['story'] = $story;
        }
        $this->swagger['paths'][$path][$method] = $data;
        if ($consumes !== null) {
            $this->swagger['paths'][$path][$method]['consumes'] = [$consumes];
        }
    }

    public function getTypeByRule($rule): string
    {
        $default = explode('|', preg_replace('/\[.*]/', '', $rule));

        if (array_intersect($default, ['int', 'lt', 'gt', 'ge', 'integer'])) {
            return 'integer';
        }
        if (array_intersect($default, ['numeric'])) {
            return 'number';
        }
        if (array_intersect($default, ['array'])) {
            return 'array';
        }
        if (array_intersect($default, ['object'])) {
            return 'object';
        }
        if (array_intersect($default, ['file'])) {
            return 'file';
        }
        return 'string';
    }

    public function makeParameters($params, $path, $method): array
    {
        $this->initModel();
        $method = ucfirst($method);
        $path = str_replace(['{', '}'], '', $path);
        $parameters = [];
        $commonHeaders = $this->config->get('apinote.common.headers', []);
        $headers = [];
        if (in_array($this->server, $commonHeaders)) {
            $headers = $commonHeaders[$this->server];
        } elseif (isset($commonHeaders['default'])) {
            $headers = $commonHeaders['default'];
        }
        foreach ($headers as $header) {
            if (is_array($header)) {
                $parameters[$header['name']] = array_merge($header, ['in' => 'header']);
            }
        }
        /** @var Query $item */
        foreach ($params as $item) {
            if ($item->rule !== null && in_array('array', explode('|', $item->rule))) {
                $item->name .= '[]';
            }
            $name = $item->name;
            if (strpos($item->name, '.')) {
                $names = explode('.', $name);
                $name = array_shift($names);
                foreach ($names as $str) {
                    $name .= "[{$str}]";
                }
            }
            $parameters[$item->name] = [
                'in' => $item->in,
                'name' => $name,
                'description' => $item->description,
                'required' => $item->required,
            ];
            if ($item instanceof Body) {
                $modelName = $method . implode('', array_map('ucfirst', explode('/', $path)));
                $this->rules2schema($modelName, $item->rules);
                $parameters[$item->name]['schema']['$ref'] = '#/definitions/' . $modelName;
            } else {
                $type = $this->getTypeByRule($item->rule);
                if ($type !== 'array') {
                    $parameters[$item->name]['type'] = $type;
                }
                $parameters[$item->name]['default'] = $item->default;
            }
        }

        return array_values($parameters);
    }

    public function makeResponses($responses, $path, $method): array
    {
        $path = str_replace(['{', '}'], '', $path);
        $templates = $this->config->get('apinote.templates', []);

        $resp = [];
        /** @var ApiResponse $item */
        foreach ($responses as $item) {
            $resp[$item->code] = [
                'description' => $item->description ?? '',
            ];
            if ($item->template && Arr::get($templates, $item->template)) {
                $json = json_encode($templates[$item->template]);
                if (!$item->schema) {
                    $item->schema = [];
                }
                $template = str_replace('"{template}"', json_encode($item->schema), $json);
                $item->schema = json_decode($template, true);
            }
            if ($item->schema) {
                if (isset($item->schema['$ref'])) {
                    $resp[$item->code]['schema']['$ref'] = '#/definitions/' . $item->schema['$ref'];
                    continue;
                }

                // 处理直接返回列表的情况 List<Integer> List<String>
                if (isset($item->schema[0]) && !is_array($item->schema[0])) {
                    $resp[$item->code]['schema']['type'] = 'array';
                    if (is_int($item->schema[0])) {
                        $resp[$item->code]['schema']['items'] = [
                            'type' => 'integer',
                        ];
                    } elseif (is_string($item->schema[0])) {
                        $resp[$item->code]['schema']['items'] = [
                            'type' => 'string',
                        ];
                    }
                    continue;
                }

                $modelName = implode('', array_map('ucfirst', explode('/', $path))) . ucfirst($method) . 'Response' . $item->code;
                $ret = $this->responseSchemaToDefinition($item->schema, $modelName);
                if ($ret) {
                    // 处理List<String, Object>
                    if (isset($item->schema[0]) && is_array($item->schema[0])) {
                        $resp[$item->code]['schema']['type'] = 'array';
                        $resp[$item->code]['schema']['items']['$ref'] = '#/definitions/' . $modelName;
                    } else {
                        $resp[$item->code]['schema']['$ref'] = '#/definitions/' . $modelName;
                    }
                }
            }
        }

        return $resp;
    }

    public function makeDefinition($definitions)
    {
        if (!$definitions) {
            return;
        }
        if ($definitions instanceof ApiDefinitions) {
            $definitions = $definitions->definitions;
        }
        foreach ($definitions as $definition) {
            /** @var ApiDefinition $definition */
            $defName = $definition->name;
            $defProps = $definition->properties;

            $formattedProps = [];

            foreach ($defProps as $propKey => $prop) {
                $propKeyArr = explode('|', $propKey);
                $propName = $propKeyArr[0];
                $propVal = [];
                isset($propKeyArr[1]) && $propVal['description'] = $propKeyArr[1];
                if (is_array($prop)) {
                    if (isset($prop['description']) && is_string($prop['description'])) {
                        $propVal['description'] = $prop['description'];
                    }

                    if (isset($prop['type']) && is_string($prop['type'])) {
                        $propVal['type'] = $prop['type'];
                    }

                    if (isset($prop['default'])) {
                        $propVal['default'] = $prop['default'];
                        $type = gettype($propVal['default']);
                        if (in_array($type, ['double', 'float'])) {
                            $type = 'number';
                        }
                        !isset($propVal['type']) && $propVal['type'] = $type;
                        $propVal['example'] = $propVal['type'] === 'number' ? 'float' : $propVal['type'];
                    }
                    if (isset($prop['$ref'])) {
                        $propVal['$ref'] = '#/definitions/' . $prop['$ref'];
                    }
                } else {
                    $propVal['default'] = $prop;
                    $type = gettype($prop);
                    if (in_array($type, ['double', 'float'])) {
                        $type = 'number';
                    }
                    $propVal['type'] = $type;
                    $propVal['example'] = $type === 'number' ? 'float' : $type;
                }
                $formattedProps[$propName] = $propVal;
            }
            $this->swagger['definitions'][$defName]['properties'] = $formattedProps;
        }
    }

    public function responseSchemaToDefinition($schema, $modelName, $level = 0)
    {
        if (!$schema) {
            return false;
        }
        $definition = [];

        // 处理 Map<String, String> Map<String, Object> Map<String, List>
        $schemaContent = $schema;
        // 处理 List<Map<String, Object>>
        if (isset($schema[0]) && is_array($schema[0])) {
            $schemaContent = $schema[0];
        }
        foreach ($schemaContent as $keyString => $val) {
            $property = [];
            $property['type'] = gettype($val);
            if (in_array($property['type'], ['double', 'float'])) {
                $property['type'] = 'number';
            }
            $keyArray = explode('|', $keyString);
            $key = $keyArray[0];
            $_key = str_replace('_', '', $key);
            $property['description'] = $keyArray[1] ?? '';
            if (is_array($val)) {
                $definitionName = $modelName . ucfirst($_key);
                if ($property['type'] === 'array' && isset($val[0])) {
                    if (is_array($val[0])) {
                        $property['type'] = 'array';
                        $ret = $this->responseSchemaToDefinition($val[0], $definitionName, 1);
                        $property['items']['$ref'] = '#/definitions/' . $definitionName;
                    } else {
                        $property['type'] = 'array';
                        $itemType = gettype($val[0]);
                        $property['items']['type'] = $itemType;
                        $property['example'] = [$itemType === 'number' ? 'float' : $itemType];
                    }
                } else {
                    // definition引用不能有type
                    unset($property['type']);
                    if (count($val) > 0) {
                        $ret = $this->responseSchemaToDefinition($val, $definitionName, 1);
                        $property['$ref'] = '#/definitions/' . $definitionName;
                    } else {
                        $property['$ref'] = '#/definitions/ModelObject';
                    }
                }
                if (isset($ret)) {
                    $this->swagger['definitions'][$definitionName] = $ret;
                }
            } else {
                $property['default'] = $val;
                $property['example'] = $property['type'] === 'number' ? 'float' : $property['type'];
            }

            $definition['properties'][$key] = $property;
        }

        if ($level === 0) {
            $this->swagger['definitions'][$modelName] = $definition;
        }

        return $definition;
    }

    public function putFile(string $file, string $content)
    {
        $pathInfo = pathinfo($file);
        if (!empty($pathInfo['dirname'])) {
            if (file_exists($pathInfo['dirname']) === false) {
                if (mkdir($pathInfo['dirname'], 0744, true) === false) {
                    return false;
                }
            }
        }
        return file_put_contents($file, $content);
    }

    public function save()
    {
        $this->swagger['tags'] = array_values($this->swagger['tags'] ?? []);
        $outputFile = $this->config->get('apinote.output_file');
        if (!$outputFile) {
            $this->logger->error('/config/autoload/apidoc.php need set output_file');
            return;
        }
        $outputFile = str_replace('{server}', $this->server, $outputFile);
        $this->putFile($outputFile, json_encode($this->swagger, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->logger->debug('Generate swagger.json success!');
    }

    private function initModel()
    {
        $arraySchema = [
            'type' => 'array',
            'required' => [],
            'items' => [
                'type' => 'string',
            ],
        ];
        $objectSchema = [
            'type' => 'object',
            'required' => [],
            'items' => [
                'type' => 'string',
            ],
        ];

        $this->swagger['definitions']['ModelArray'] = $arraySchema;
        $this->swagger['definitions']['ModelObject'] = $objectSchema;
    }

    private function rules2schema($name, $rules)
    {
        $schema = [
            'type' => 'object',
            'properties' => [],
        ];
        foreach ($rules as $field => $rule) {
            $type = null;
            $property = [];

            $fieldNameLabel = explode('|', $field);
            $fieldName = $fieldNameLabel[0];
            if (strpos($fieldName, '.')) {
                $fieldNames = explode('.', $fieldName);
                $fieldName = array_shift($fieldNames);
                $endName = array_pop($fieldNames);
                $fieldNames = array_reverse($fieldNames);
                $newRules = '{"' . $endName . '|' . $fieldNameLabel[1] . '":"' . $rule . '"}';
                foreach ($fieldNames as $v) {
                    if ($v === '*') {
                        $newRules = '[' . $newRules . ']';
                    } else {
                        $newRules = '{"' . $v . '":' . $newRules . '}';
                    }
                }
                $rule = json_decode($newRules, true);
            }
            if (is_array($rule)) {
                $deepModelName = $name . ucfirst($fieldName);
                if (Arr::isAssoc($rule)) {
                    $this->rules2schema($deepModelName, $rule);
                    $property['$ref'] = '#/definitions/' . $deepModelName;
                } else {
                    $type = 'array';
                    $this->rules2schema($deepModelName, $rule[0]);
                    $property['items']['$ref'] = '#/definitions/' . $deepModelName;
                }
            } else {
                $type = $this->getTypeByRule($rule);
                if ($type === 'string') {
                    in_array('required', explode('|', $rule)) && $schema['required'][] = $fieldName;
                }
                if ($type == 'array') {
                    $property['$ref'] = '#/definitions/ModelArray';
                }
                if ($type == 'object') {
                    $property['$ref'] = '#/definitions/ModelObject';
                }
            }
            if ($type !== null) {
                $property['type'] = $type;
                if (!in_array($type, ['array', 'object'])) {
                    $property['example'] = $type;
                }
            }
            $property['description'] = $fieldNameLabel[1] ?? '';

            $schema['properties'][$fieldName] = $property;
        }
        $this->swagger['definitions'][$name] = $schema;
    }
}
