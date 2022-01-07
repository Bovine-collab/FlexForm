<?php
/**
 * Created by  : Designburo.nl
 * Project     : csp
 * Filename    : instance.class.php
 * Description :
 * Date        : 6-8-2021
 * Time        : 08:44
 */

namespace WSForm\Render\Themes\WSForm;

use MediaWiki\Revision\RevisionRecord;
use MWException;
use RequestContext;
use WSForm\Core\Core;
use WSForm\Processors\Content\Edit;
use WSForm\Render\Themes\InstanceRenderer;

class WSFormInstanceRenderer implements InstanceRenderer {
    private static function getArg( $name, $args ) {
		if ( isset( $args[$name] ) && $args[$name] !== '' ) {
			return $args[$name];
		} else {
			return false;
		}
	}

    /**
     * @inheritDoc
     * @throws MWException
     */
	public function render_instance( string $content, array $args ): string {
		// TODO: Move some of this logic to the caller

        if ( !RequestContext::getMain()->canUseWikiPage() ) {
            return "";
        }

		$instance = self::instanceDefault( $args );
		$pageWikiObject = RequestContext::getMain()->getWikiPage();
		$textAreaContent = $instance['txtareacontent'];

		if ( $pageWikiObject->exists() ) {
			$pageContent = $pageWikiObject->getContent( RevisionRecord::RAW )->getText();
			$edit = new Edit();

			if ( $instance['templateParent'] !== 'none' ) {
				$templateContent = $edit->getTemplate(
					$pageContent,
					$instance['templateParent']
				);
			} else {
				$templateContent = $pageContent;
			}

			$expl = Edit::pregExplode( $templateContent );

			foreach ( $expl as $k => $variable ) {
				$tmp = explode(
					'=',
					$variable
				);

				if ( trim( $tmp[0] ) === trim( $instance['instanceName'] ) ) {
					$textAreaContent .= '{{' . $instance['template'] . self::get_string_between(
							$expl[ $k ],
							'{{' . $instance['template'],
							'}}'
						) . '}}';
				}
			}
		}

		$ret = self::renderInstanceHtml(
			$instance,
			$content,
			$textAreaContent
		);

		$instanceSettings = array(
			'draggable'         => $instance['draggable'],
			'addButtonClass'    => "." . $instance['addButtonClass'],
			'removeButtonClass' => "." . $instance['removeButtonClass'],
			'handleClass'       => "." . $instance['handleClass'],
			'selector'          => "." . $instance['selector'],
			'textarea'          => "." . $instance['textarea'],
			'list'              => "." . $instance['list'],
			'copy'              => "." . $instance['copy'],
		);

		$out = RequestContext::getMain()->getOutput();
		$out->addJsConfigVars( array( "wsinstance" => $instanceSettings ) );

		if ( ! Core::isLoaded( 'wsinstance' ) ) {
			$js = 'wachtff( startInstance, true );';
            Core::includeInlineScript( $js );
            Core::addAsLoaded( 'wsinstance' );
		}

		return $ret;
	}

	private static function get_string_between( $string, $start, $end ) {
		$string = " " . $string;
		$ini    = strpos(
			$string,
			$start
		);
		if ( $ini == 0 ) {
			return "";
		}
		$ini += strlen( $start );
		$len = strrpos(
				   $string,
				   $end,
				   $ini
			   ) - $ini;

		return substr(
			$string,
			$ini,
			$len
		);
	}

	private static function renderInstanceHtml( $instance, $innerHtml, $textAreaContent ) {
		$ret = '<div class="' . $instance['selector'] . '">' . PHP_EOL;
		$ret .= '<div class="hidden">' . PHP_EOL;
		$ret .= '<textarea rows="10" name="' . $instance['instanceName'] . '"  class="hidden ' . $instance['textarea'] . '" data-template="' . $instance['template'] . '">' . $textAreaContent . '</textarea>' . PHP_EOL;

		if ( Core::isShowOnSelectActive() ) {
			$ret .= '<div class="' . $instance['copy'] . ' ' . $instance['copyExtra'] . ' WSShowOnSelect">' . PHP_EOL;
		} else {
			$ret .= '<div class="' . $instance['copy'] . ' ' . $instance['copyExtra'] . '">' . PHP_EOL;
		}

		if ( $instance['handleClassExtra'] === 'none' ) {
			$instance['handleClassExtra'] = '';
		}

		if ( $instance['addButtonClassExtra'] === 'none' ) {
			$instance['addButtonClassExtra'] = '';
		}

		if ( $instance['removeButtonClassExtra'] === 'none' ) {
			$instance['removeButtonClassExtra'] = '';
		}

		if ( $instance['handleClass'] !== 'none' ) {
			$ret .= '<span class="' . $instance['handleClass'] . ' ' . $instance['handleClassExtra'] . '"></span>';
		}

		if ( $instance['removeButtonClass'] !== 'none' ) {
			$ret .= '<button type="button" class="' . $instance['removeButtonClass'] . ' ' . $instance['removeButtonClassExtra'] . '" role="button"></button>';
		}

		if ( $instance['addButtonClass'] !== 'none' ) {
			$addBtn = '<button type="button" class="' . $instance['addButtonClass'] . ' ' . $instance['addButtonClassExtra'] . '" role="button"></button>';
			$ret .= $addBtn;
		}

		$ret .= $innerHtml;
		$ret .= '</div>';
		$ret .= '</div>';

		$ret .= PHP_EOL . '<div class="' . $instance['list'] . '"></div>' . PHP_EOL;

		if( $instance['buttonBottom'] !== 'none' ) {
			$ret .= PHP_EOL . '<p><button type="button" class="' . $instance['addButtonTopBottomClass'] . '" role="button">' . $instance['buttonBottom'] . '</button></p>';
		}

		$ret .= '</div>' . PHP_EOL;

		return $ret;
	}

    private static function instanceDefault( $args ) {
        $defaultInstance = array(
            'selector'               => "WSmultipleTemplateWrapper",
            'copy'                   => "WSmultipleTemplateMain",
            'textarea'               => 'WSmultipleTemplateField',
            'list'                   => 'WSmultipleTemplateList',
            'addButtonClass'         => "WSmultipleTemplateAddAbove",
            'addButtonTopBottomClass'=> "WSmultipleTemplateAddBelow",
            'addButtonClassExtra'    => "wsform-instance-add-btn",
            'removeButtonClass'      => "WSmultipleTemplateDel",
            'removeButtonClassExtra' => "wsform-instance-delete-btn",
            'handleClass'            => 'ws-sortable-handle',
            'handleClassExtra'       => 'wsform-instance-move-handle',
            'instanceMoveClass'      => 'ws-formgroup-sortable',
            'draggable'              => false,
            'instanceName'           => '',
            'template'               => "",
            'templateParent'         => "",
            'txtareacontent'         => '',
            'buttonBottom'			 => 'Add Row',
            'copyExtra'              => 'wsform-instance-record'
        );

        $defaultTranslator = array(
            'template'            => 'template',
            'template-parent'     => 'templateParent',
            'name'                => 'instanceName',
            'button-add'          => 'addButtonClass',
            'button-remove'       => 'removeButtonClass',
            'button-move'         => 'handleClass',
            'button-add-extra'    => 'addButtonClassExtra',
            'button-remove-extra' => 'removeButtonClassExtra',
            'button-move-extra'   => 'handleClassExtra',
            'wrapper-instance'    => 'copy',
            'wrapper-main'        => 'selector',
            'wrapper-main-extra'  => 'copyExtra',
            'instance-storage'    => 'textarea',
            'instance-list'       => 'list',
            'default-content'     => 'txtareacontent',
            'add-button-on-bottom'=> 'buttonBottom'
        );

        foreach ( $defaultTranslator as $from => $to ) {
            $val = self::getArg(
                $from,
                $args
            );
            if ( $val !== false ) {
                switch ( $from ) {
                    case "button-move":
                        if( strtolower( $val ) === "none" ) {
                            $defaultInstance['draggable'] = false;
                        }

                        break;
                }
                $defaultInstance[$to] = $val;
            } else {
                switch ( $from ) {
                    case "button-move":
                        $defaultInstance['draggable'] = true;
                        break;

                    case "templateParent":
                        $defaultInstance[$to] = "none";
                        break;
                }
            }
        }

        return $defaultInstance;
    }
}