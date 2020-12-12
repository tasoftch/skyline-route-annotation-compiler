<?php
/**
 * BSD 3-Clause License
 *
 * Copyright (c) 2019, TASoft Applications
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 *  Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 *  Neither the name of the copyright holder nor the names of its
 *   contributors may be used to endorse or promote products derived from
 *   this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace Skyline\Compiler\Annotation\Compiler;


use Skyline\Compiler\CompilerConfiguration;
use Skyline\Compiler\CompilerContext;
use Skyline\Expose\Compiler\AbstractAnnotationCompiler;
use Skyline\Compiler\Helper\ModuleStorageHelper;
use Skyline\Router\AbstractRouterPlugin;

class ResolvesRoutAnnotationsCompiler extends AbstractAnnotationCompiler
{
    private $routingFile;
    public function __construct(string $compilerID, string $routingFile = "", bool $excludeMagicMethods = true)
    {
        parent::__construct($compilerID, $excludeMagicMethods);
        $this->routingFile = $routingFile;
    }

    public function compile(CompilerContext $context)
    {
    	$storage = new ModuleStorageHelper();

        foreach($this->yieldClasses("ACTIONCONTROLLER") as $controller) {

            $list = $this->findClassMethods($controller, self::OPT_PUBLIC_OBJECTIVE);

            if($list) {
                foreach($list as $name => $method) {
                    $annots = $this->getAnnotationsOfMethod($method, true);
                    if($annots) {
                        $actionInfo = [
                            AbstractRouterPlugin::ROUTED_CONTROLLER_KEY => $controller,
                            AbstractRouterPlugin::ROUTED_METHOD_KEY => $method->getName()
                        ];

                        if($module = $this->getDeclaredModule($controller)) {
                            $storage->pushModule($module);
                            $actionInfo[ AbstractRouterPlugin::ROUTED_MODULE_KEY ] = $module;
                        } elseif($module = $annots["module"]) {
							$module = array_shift($module);
							$storage->pushModule($module);
							$actionInfo[ AbstractRouterPlugin::ROUTED_MODULE_KEY ] = $module;
						}

                        if($render = $annots["render"] ?? NULL) {
                            $actionInfo[ AbstractRouterPlugin::ROUTED_RENDER_KEY ] = array_shift($render);
                        }

                        if($routes = $annots["route"] ?? NULL) {
                            $route = array_shift($routes);
                            if(preg_match("/^\s*(literal|regex)\s+(.*?)\s*$/i", $route, $ms)) {
                                if(strtolower($ms[1]) == 'literal')
                                    $storage[ AbstractRouterPlugin::URI_ROUTE ][$ms[2]] = $actionInfo;
                                elseif(strtolower($ms[1]) == 'regex')
									$storage[ AbstractRouterPlugin::REGEX_URI_ROUTE ][$ms[2]] = $actionInfo;
                                else
                                    trigger_error("@route $ms[1] is not valid", E_USER_WARNING);
                            }
                        }

						$storage->popModule();
                    }
                }
            }
        }

        $storage->resetModule();

        if(count($storage)) {
            $dir = $context->getSkylineAppDirectory(CompilerConfiguration::SKYLINE_DIR_COMPILED);
            $routings = require "$dir/$this->routingFile";

            $tokens = token_get_all( file_get_contents("$dir/$this->routingFile") );
            $comment = "";
            foreach($tokens as $token) {
                if(is_array($token) && $token[0] == T_COMMENT) {
                    $comment = $token[1];
                    break;
                }
            }

            if(preg_match_all("/^\s*\*\s*(\d+)\.\s+(.*?)$/im", $comment, $ms)) {
                $comment = "/*\n *\t== TASoft Config Compiler ==\n *\tCompiled from:\n";
                foreach($ms[1] as $idx => $nr)
                    $comment .= " *\t$nr.\t" . $ms[2][$idx] . "\n";

                $nr++;
                $comment .= " *\t$nr.\tGeneric Annotation Compiler\n */\n";
            }

            foreach($routings as $type => $listings) {
                foreach($listings as $info => $listing) {
                    $storage[$type][$info] = $listing;
                }
            }

            $data = $storage->exportStorage($comment);

            file_put_contents("$dir/$this->routingFile", $data);
        }
    }

    public function getCompilerName(): string
    {
        return "Resolve Route Annotations Compiler";
    }
}