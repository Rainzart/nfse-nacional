<?php

namespace Hadder\NfseNacional;

/**
 * Class for RPS construction and validation of data
 *
 */

use DOMException;
use DOMNode;
use NFePHP\Common\DOMImproved as Dom;
use stdClass;


class Dps implements DpsInterface
{
    /**
     * @var stdClass
     */
    public $std;
    /**
     * @var DOMNode
     */
    protected $dps;
    /**
     * @var DOMNode
     */
    protected $evento;
    /**
     * @var string
     */
    protected $jsonschema;
    /**
     * @var Dom
     */
    protected $dom;
    private string $dpsId;
    private string $preId;

    /**
     * Constructor
     * @param stdClass|null $std
     * @throws DOMException
     */
    public function __construct(stdClass $std = null)
    {
        $this->init($std);
        $this->dom = new Dom('1.0', 'UTF-8');
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput = false;
    }

    /**
     *
     * @param stdClass|null $dps
     */
    private function init(stdClass $dps = null)
    {
        if (!empty($dps)) {
            $this->std = $this->propertiesToLower($dps);
            if (empty($this->std->version)) {
                $this->std->version = '1.01';
            }
            //$ver = str_replace('.', '_', $this->std->version);
            //$this->jsonschema = realpath("../storage/jsonSchemes/v$ver/rps.schema");
            //$this->validInputData();
        }
    }

    public function render(stdClass $std = null)
    {
        if ($this->dom->hasChildNodes()) {
            $this->dom = new Dom('1.0', 'UTF-8');
            $this->dom->preserveWhiteSpace = false;
            $this->dom->formatOutput = false;
        }

        $this->init($std);
        $this->dps = $this->dom->createElement('DPS');
        $this->dps->setAttribute('versao', $this->std->version);
        $this->dps->setAttribute('xmlns', 'http://www.sped.fazenda.gov.br/nfse');

        $infdps_inner = $this->dom->createElement('infDPS');
        $infdps_inner->setAttribute('Id', $this->generateId());

        $this->dom->addChild(
            $infdps_inner,
            'tpAmb',
            $this->std->infdps->tpamb,
            true
        );
        $this->dom->addChild(
            $infdps_inner,
            'dhEmi',
            $this->std->infdps->dhemi,
            true
        );
        $this->dom->addChild(
            $infdps_inner,
            'verAplic',
            $this->std->infdps->veraplic,
            true
        );
        $this->dom->addChild(
            $infdps_inner,
            'serie',
            $this->std->infdps->serie,
            true
        );
        $this->dom->addChild(
            $infdps_inner,
            'nDPS',
            $this->std->infdps->ndps,
            true
        );
        $this->dom->addChild(
            $infdps_inner,
            'dCompet',
            $this->std->infdps->dcompet,
            true
        );
        $this->dom->addChild(
            $infdps_inner,
            'tpEmit',
            $this->std->infdps->tpemit,
            true
        );
        if (isset($this->std->infdps->cmotivoemisti)) {
            $this->dom->addChild(
                $infdps_inner,
                'cMotivoEmisTI',
                $this->std->infdps->cmotivoemisti
            );
        }
        if (isset($this->std->infdps->chnfserej)) {
            $this->dom->addChild(
                $infdps_inner,
                'chNFSeRej',
                $this->std->infdps->chnfserej
            );
        }
        $this->dom->addChild(
            $infdps_inner,
            'cLocEmi',
            $this->std->infdps->clocemi,
            true
        );

        if (isset($this->std->infdps->subst)) {
            $subst_inner = $this->dom->createElement('subst');
            $infdps_inner->appendChild($subst_inner);
            $this->dom->addChild(
                $subst_inner,
                'chSubstda',
                $this->std->infdps->subst->chsubstda,
                true
            );
            $this->dom->addChild(
                $subst_inner,
                'cMotivo',
                $this->std->infdps->subst->cmotivo,
                true
            );
            $this->dom->addChild(
                $subst_inner,
                'xMotivo',
                $this->std->infdps->subst->xmotivo,
                true
            );
        }

        if (isset($this->std->infdps->prest)) {
            $prest_inner = $this->dom->createElement('prest');
            $infdps_inner->appendChild($prest_inner);
            if (isset($this->std->infdps->prest->cnpj)) {
                $this->dom->addChild(
                    $prest_inner,
                    'CNPJ',
                    $this->std->infdps->prest->cnpj,
                    true
                );
            }
            if (isset($this->std->infdps->prest->cpf)) {
                $this->dom->addChild(
                    $prest_inner,
                    'CPF',
                    $this->std->infdps->prest->cpf,
                    true
                );
            }
            if (isset($this->std->infdps->prest->nif)) {
                $this->dom->addChild(
                    $prest_inner,
                    'NIF',
                    $this->std->infdps->prest->nif,
                    true
                );
            }
            if (isset($this->std->infdps->prest->cnaonif)) {
                $this->dom->addChild(
                    $prest_inner,
                    'cNaoNIF',
                    $this->std->infdps->prest->cnaonif,
                    true
                );
            }
            if (isset($this->std->infdps->prest->caepf)) {
                $this->dom->addChild(
                    $prest_inner,
                    'CAEPF',
                    $this->std->infdps->prest->caepf,
                    true
                );
            }
            if (isset($this->std->infdps->prest->im)) {
                $this->dom->addChild(
                    $prest_inner,
                    'IM',
                    $this->std->infdps->prest->im,
                    true
                );
            }
            if (isset($this->std->infdps->prest->xnome)) {
                $this->dom->addChild(
                    $prest_inner,
                    'xNome',
                    $this->std->infdps->prest->xnome,
                    true
                );
            }
            if (isset($this->std->infdps->prest->end)) {
                $end_inner = $this->dom->createElement('end');
                $prest_inner->appendChild($end_inner);
                if (isset($this->std->infdps->prest->end->endnac)) {
                    $endnac_inner = $this->dom->createElement('endNac');
                    $end_inner->appendChild($endnac_inner);
                    $this->dom->addChild(
                        $endnac_inner,
                        'cMun',
                        $this->std->infdps->prest->end->endnac->cmun,
                        true
                    );
                    $this->dom->addChild(
                        $endnac_inner,
                        'CEP',
                        $this->std->infdps->prest->end->endnac->cep,
                        true
                    );
                } elseif (isset($this->std->infdps->prest->end->endext)) {
                    $endext_inner = $this->dom->createElement('endExt');
                    $end_inner->appendChild($endext_inner);
                    $this->dom->addChild(
                        $endext_inner,
                        'cPais',
                        $this->std->infdps->prest->end->endext->cpais,
                        true
                    );
                    $this->dom->addChild(
                        $endext_inner,
                        'cEndPost',
                        $this->std->infdps->prest->end->endext->cendpost,
                        true
                    );
                    $this->dom->addChild(
                        $endext_inner,
                        'xCidade',
                        $this->std->infdps->prest->end->endext->xcidade,
                        true
                    );
                    $this->dom->addChild(
                        $endext_inner,
                        'xEstProvReg',
                        $this->std->infdps->prest->end->endext->xestprovreg,
                        true
                    );
                }

                //                dd($this->std->infdps->prest->end);
                $this->dom->addChild(
                    $end_inner,
                    'xLgr',
                    $this->std->infdps->prest->end->xlgr,
                    true
                );
                $this->dom->addChild(
                    $end_inner,
                    'nro',
                    $this->std->infdps->prest->end->nro,
                    true
                );
                if (isset($this->std->infdps->prest->end->xcpl)) {
                    $this->dom->addChild(
                        $end_inner,
                        'xCpl',
                        $this->std->infdps->prest->end->xcpl
                    );
                }
                $this->dom->addChild(
                    $end_inner,
                    'xBairro',
                    $this->std->infdps->prest->end->xbairro,
                    true
                );
            }
            if (isset($this->std->infdps->prest->fone)) {
                $this->dom->addChild(
                    $prest_inner,
                    'fone',
                    $this->std->infdps->prest->fone
                );
            }
            if (isset($this->std->infdps->prest->email)) {
                $this->dom->addChild(
                    $prest_inner,
                    'email',
                    $this->std->infdps->prest->email
                );
            }

            $regtrib_inner = $this->dom->createElement('regTrib');
            $prest_inner->appendChild($regtrib_inner);
            $this->dom->addChild(
                $regtrib_inner,
                'opSimpNac',
                $this->std->infdps->prest->regtrib->opsimpnac,
                true
            );
            if (isset($this->std->infdps->prest->regtrib->regaptribsn)) {
                $this->dom->addChild(
                    $regtrib_inner,
                    'regApTribSN',
                    $this->std->infdps->prest->regtrib->regaptribsn
                );
            }
            $this->dom->addChild(
                $regtrib_inner,
                'regEspTrib',
                $this->std->infdps->prest->regtrib->regesptrib,
                true
            );

        }
        if (isset($this->std->infdps->toma)) {
            $toma_inner = $this->dom->createElement('toma');
            $infdps_inner->appendChild($toma_inner);
            if (isset($this->std->infdps->toma->cnpj)) {
                $this->dom->addChild(
                    $toma_inner,
                    'CNPJ',
                    $this->std->infdps->toma->cnpj,
                    true
                );
            }
            if (isset($this->std->infdps->toma->cpf)) {
                $this->dom->addChild(
                    $toma_inner,
                    'CPF',
                    $this->std->infdps->toma->cpf,
                    true
                );
            }
            if (isset($this->std->infdps->toma->nif)) {
                $this->dom->addChild(
                    $toma_inner,
                    'NIF',
                    $this->std->infdps->toma->nif,
                    true
                );
            }
            if (isset($this->std->infdps->toma->cnaonif)) {
                $this->dom->addChild(
                    $toma_inner,
                    'cNaoNIF',
                    $this->std->infdps->toma->cnaonif,
                    true
                );
            }
            if (isset($this->std->infdps->toma->caepf)) {
                $this->dom->addChild(
                    $toma_inner,
                    'CAEPF',
                    $this->std->infdps->toma->caepf,
                    true
                );
            }
            if (isset($this->std->infdps->toma->im)) {
                $this->dom->addChild(
                    $toma_inner,
                    'IM',
                    $this->std->infdps->toma->im,
                    true
                );
            }
            $this->dom->addChild(
                $toma_inner,
                'xNome',
                $this->std->infdps->toma->xnome,
                true
            );
            if (isset($this->std->infdps->toma->end)) {
                $end_inner = $this->dom->createElement('end');
                $toma_inner->appendChild($end_inner);
                if (isset($this->std->infdps->toma->end->endnac)) {
                    $endnac_inner = $this->dom->createElement('endNac');
                    $end_inner->appendChild($endnac_inner);
                    $this->dom->addChild(
                        $endnac_inner,
                        'cMun',
                        $this->std->infdps->toma->end->endnac->cmun,
                        true
                    );
                    $this->dom->addChild(
                        $endnac_inner,
                        'CEP',
                        $this->std->infdps->toma->end->endnac->cep,
                        true
                    );
                } elseif (isset($this->std->infdps->toma->end->endext)) {
                    $endext_inner = $this->dom->createElement('endExt');
                    $end_inner->appendChild($endext_inner);
                    $this->dom->addChild(
                        $endext_inner,
                        'cPais',
                        $this->std->infdps->toma->end->endext->cpais,
                        true
                    );
                    $this->dom->addChild(
                        $endext_inner,
                        'cEndPost',
                        $this->std->infdps->toma->end->endext->cendpost,
                        true
                    );
                    $this->dom->addChild(
                        $endext_inner,
                        'xCidade',
                        $this->std->infdps->toma->end->endext->xcidade,
                        true
                    );
                    $this->dom->addChild(
                        $endext_inner,
                        'xEstProvReg',
                        $this->std->infdps->toma->end->endext->xestprovreg,
                        true
                    );
                }
                $this->dom->addChild(
                    $end_inner,
                    'xLgr',
                    $this->std->infdps->toma->end->xlgr,
                    true
                );
                $this->dom->addChild(
                    $end_inner,
                    'nro',
                    $this->std->infdps->toma->end->nro,
                    true
                );
                if (isset($this->std->infdps->toma->end->xcpl)) {
                    $this->dom->addChild(
                        $end_inner,
                        'xCpl',
                        $this->std->infdps->toma->end->xcpl,
                        false
                    );
                }
                $this->dom->addChild(
                    $end_inner,
                    'xBairro',
                    $this->std->infdps->toma->end->xbairro,
                    true
                );
            }
            if (isset($this->std->infdps->toma->fone)) {
                $this->dom->addChild(
                    $toma_inner,
                    'fone',
                    $this->std->infdps->toma->fone
                );
            }
            if (isset($this->std->infdps->toma->email)) {
                $this->dom->addChild(
                    $toma_inner,
                    'email',
                    $this->std->infdps->toma->email
                );
            }
        }

        //TODO Fazer grupo interm
        //if (isset($this->std->interm)) {
        //    $interm_inner = $this->dom->createElement('interm');
        //    $infdps_inner->appendChild($interm_inner);
        //}

        $serv_inner = $this->dom->createElement('serv');
        $infdps_inner->appendChild($serv_inner);

        $locprest_inner = $this->dom->createElement('locPrest');
        $serv_inner->appendChild($locprest_inner);
        $this->dom->addChild(
            $locprest_inner,
            'cLocPrestacao',
            $this->std->infdps->serv->locprest->clocprestacao,
            true
        );
        if (isset($this->std->infdps->serv->locprest->cpaisprestacao)) {
            $this->dom->addChild(
                $locprest_inner,
                'cPaisPrestacao',
                $this->std->infdps->serv->locprest->cpaisprestacao,
                true
            );
        }

        $cserv_inner = $this->dom->createElement('cServ');
        $serv_inner->appendChild($cserv_inner);

        $this->dom->addChild(
            $cserv_inner,
            'cTribNac',
            $this->std->infdps->serv->cserv->ctribnac,
            true
        );
        if (isset($this->std->infdps->serv->cserv->ctribmun)) {
            $this->dom->addChild(
                $cserv_inner,
                'cTribMun',
                $this->std->infdps->serv->cserv->ctribmun,
                true
            );
        }
        $this->dom->addChild(
            $cserv_inner,
            'xDescServ',
            $this->std->infdps->serv->cserv->xdescserv,
            true
        );
        if (isset($this->std->infdps->serv->cserv->cnbs)) {
            $this->dom->addChild(
                $cserv_inner,
                'cNBS',
                $this->std->infdps->serv->cserv->cnbs,
                true
            );
        }
        if (isset($this->std->infdps->serv->cserv->cintcontrib)) {
            $this->dom->addChild(
                $cserv_inner,
                'cIntContrib',
                $this->std->infdps->serv->cserv->cintcontrib,
                true
            );
        }

        //grupo comExt
        if (isset($this->std->infdps->serv->comext)) {
            $comext_inner = $this->dom->createElement('comExt');
            $serv_inner->appendChild($comext_inner);

            $this->dom->addChild(
                $comext_inner,
                'mdPrestacao',
                $this->std->infdps->serv->comext->mdprestacao
            );

            $this->dom->addChild(
                $comext_inner,
                'vincPrest',
                $this->std->infdps->serv->comext->vincprest
            );

            $this->dom->addChild(
                $comext_inner,
                'tpMoeda',
                $this->std->infdps->serv->comext->tpmoeda
            );

            $this->dom->addChild(
                $comext_inner,
                'vServMoeda',
                $this->std->infdps->serv->comext->vservmoeda
            );

            $this->dom->addChild(
                $comext_inner,
                'mecAFComexP',
                $this->std->infdps->serv->comext->mecafcomexp
            );

            $this->dom->addChild(
                $comext_inner,
                'mecAFComexT',
                $this->std->infdps->serv->comext->mecafcomext
            );

            $this->dom->addChild(
                $comext_inner,
                'movTempBens',
                $this->std->infdps->serv->comext->movtempbens
            );

            if (isset($this->std->infdps->serv->comext->ndi)) {
                $this->dom->addChild(
                    $comext_inner,
                    'nDI',
                    $this->std->infdps->serv->comext->ndi
                );
            }

            if (isset($this->std->infdps->serv->comext->nre)) {
                $this->dom->addChild(
                    $comext_inner,
                    'nRE',
                    $this->std->infdps->serv->comext->nre
                );
            }


            $this->dom->addChild(
                $comext_inner,
                'mdic',
                $this->std->infdps->serv->comext->mdic
            );
        }

        // Monta grupo obra
        if (isset($this->std->infdps->serv->obra)) {
            $obra_inner = $this->dom->createElement('obra');
            $serv_inner->appendChild($obra_inner);

            // inscImobFisc (opcional)
            if (isset($this->std->infdps->serv->obra->inscimobfisc)) {
                $this->dom->addChild(
                    $obra_inner,
                    'inscImobFisc',
                    $this->std->infdps->serv->obra->inscimobfisc,
                    true
                );
            }

            // cObra (normalmente obrigatório quando obra existir)
            if (isset($this->std->infdps->serv->obra->cobra)) {
                $this->dom->addChild(
                    $obra_inner,
                    'cObra',
                    $this->std->infdps->serv->obra->cobra,
                    true
                );
            }

            // cCIB (validação do layout pode rejeitar se inválido)
            if (isset($this->std->infdps->serv->obra->ccib)) {
                $this->dom->addChild(
                    $obra_inner,
                    'cCIB',
                    $this->std->infdps->serv->obra->ccib,
                    true
                );
            }

            // end (endereço nacional)
            if (isset($this->std->infdps->serv->obra->end)) {
                $end_obra_inner = $this->dom->createElement('end');
                $obra_inner->appendChild($end_obra_inner);

                if (isset($this->std->infdps->serv->obra->end->cep)) {
                    $this->dom->addChild(
                        $end_obra_inner,
                        'CEP',
                        $this->std->infdps->serv->obra->end->cep,
                        true
                    );
                }

                if (isset($this->std->infdps->serv->obra->end->xlgr)) {
                    $this->dom->addChild(
                        $end_obra_inner,
                        'xLgr',
                        $this->std->infdps->serv->obra->end->xlgr,
                        true
                    );
                }

                if (isset($this->std->infdps->serv->obra->end->nro)) {
                    $this->dom->addChild(
                        $end_obra_inner,
                        'nro',
                        $this->std->infdps->serv->obra->end->nro,
                        true
                    );
                }

                // xCpl (opcional)
                if (isset($this->std->infdps->serv->obra->end->xcpl)) {
                    $this->dom->addChild(
                        $end_obra_inner,
                        'xCpl',
                        $this->std->infdps->serv->obra->end->xcpl,
                        true
                    );
                }

                if (isset($this->std->infdps->serv->obra->end->xbairro)) {
                    $this->dom->addChild(
                        $end_obra_inner,
                        'xBairro',
                        $this->std->infdps->serv->obra->end->xbairro,
                        true
                    );
                }
            }
        }

        //TODO Fazer grupo lsadppu
        if (isset($this->std->infdps->serv->atvevento)) {
            $atvEvento_inner = $this->dom->createElement('atvEvento');
            $serv_inner->appendChild($atvEvento_inner);

            // Campo: xNome
            if (isset($this->std->infdps->serv->atvevento->xnome)) {
                $this->dom->addChild(
                    $atvEvento_inner,
                    'xNome',
                    $this->std->infdps->serv->atvevento->xnome,
                    true
                );
            }

            // Campo: dtIni
            if (isset($this->std->infdps->serv->atvevento->dtini)) {
                $this->dom->addChild(
                    $atvEvento_inner,
                    'dtIni',
                    $this->std->infdps->serv->atvevento->dtini,
                    true
                );
            }

            // Campo: dtFim
            if (isset($this->std->infdps->serv->atvevento->dtfim)) {
                $this->dom->addChild(
                    $atvEvento_inner,
                    'dtFim',
                    $this->std->infdps->serv->atvevento->dtfim,
                    true
                );
            }

            // Endereço do evento
            if (isset($this->std->infdps->serv->atvevento->end)) {
                $end_evento_inner = $this->dom->createElement('end');
                $atvEvento_inner->appendChild($end_evento_inner);

                if (isset($this->std->infdps->serv->atvevento->end->cep)) {
                    $this->dom->addChild(
                        $end_evento_inner,
                        'CEP',
                        $this->std->infdps->serv->atvevento->end->cep,
                        true
                    );
                }

                if (isset($this->std->infdps->serv->atvevento->end->xlgr)) {
                    $this->dom->addChild(
                        $end_evento_inner,
                        'xLgr',
                        $this->std->infdps->serv->atvevento->end->xlgr,
                        true
                    );
                }

                if (isset($this->std->infdps->serv->atvevento->end->nro)) {
                    $this->dom->addChild(
                        $end_evento_inner,
                        'nro',
                        $this->std->infdps->serv->atvevento->end->nro,
                        true
                    );
                }

                if (isset($this->std->infdps->serv->atvevento->end->xbairro)) {
                    $this->dom->addChild(
                        $end_evento_inner,
                        'xBairro',
                        $this->std->infdps->serv->atvevento->end->xbairro,
                        true
                    );
                }
            }
        }
        //TODO Fazer grupo explRod


        // Grupo de informações complementares disponível para todos os serviços prestados
        if (isset($this->std->infdps->serv->infocompl->iddoctec)) {
            if (!isset($infocompl_inner)) {
                $infocompl_inner = $this->dom->createElement('infoCompl');
                $serv_inner->appendChild($infocompl_inner);
            }
            $this->dom->addChild(
                $infocompl_inner,
                'idDocTec',
                $this->std->infdps->serv->infocompl->iddoctec,
                true
            );
        }
        if (isset($this->std->infdps->serv->infocompl->docref)) {
            if (!isset($infocompl_inner)) {
                $infocompl_inner = $this->dom->createElement('infoCompl');
                $serv_inner->appendChild($infocompl_inner);
            }
            $this->dom->addChild(
                $infocompl_inner,
                'docRef',
                $this->std->infdps->serv->infocompl->docref,
                true
            );
        }
        if (isset($this->std->infdps->serv->infocompl->xped)) {
            if (!isset($infocompl_inner)) {
                $infocompl_inner = $this->dom->createElement('infoCompl');
                $serv_inner->appendChild($infocompl_inner);
            }
            $this->dom->addChild(
                $infocompl_inner,
                'xPed',
                $this->std->infdps->serv->infocompl->xped,
                true
            );
        }
        if (isset($this->std->infdps->serv->infocompl->gitemped->xitemped)) {
            if (!isset($infocompl_inner)) {
                $infocompl_inner = $this->dom->createElement('infoCompl');
                $serv_inner->appendChild($infocompl_inner);
            }
            $gItemPed_inner = $this->dom->createElement('gItemPed');
            $infocompl_inner->appendChild($gItemPed_inner);
            $this->dom->addChild(
                $gItemPed_inner,
                'xItemPed',
                $this->std->infdps->serv->infocompl->gitemped->xitemped,
                true
            );
        }
        if (isset($this->std->infdps->serv->infocompl->xinfcomp)) {
            if (!isset($infocompl_inner)) {
                $infocompl_inner = $this->dom->createElement('infoCompl');
                $serv_inner->appendChild($infocompl_inner);
            }
            $this->dom->addChild(
                $infocompl_inner,
                'xInfComp',
                $this->std->infdps->serv->infocompl->xinfcomp,
                true
            );
        }

        $valores_inner = $this->dom->createElement('valores');
        $infdps_inner->appendChild($valores_inner);
        $vservprest_inner = $this->dom->createElement('vServPrest');
        $valores_inner->appendChild($vservprest_inner);

        if (isset($this->std->infdps->valores->vservprest->vreceb)) {
            $this->dom->addChild(
                $vservprest_inner,
                'vReceb',
                $this->std->infdps->valores->vservprest->vreceb
            );
        }
        $this->dom->addChild(
            $vservprest_inner,
            'vServ',
            $this->std->infdps->valores->vservprest->vserv,
            true
        );

        //TODO Fazer grupo vDescCondIncond
		// Grupo vDescCondIncond (dentro de <valores>)
		$vDescIncond = $this->std->infdps->valores->vdesccondincond->vdescincond ?? null;
		$vDescCond   = $this->std->infdps->valores->vdesccondincond->vdesccond   ?? null;

		// regra: considera vazio se null, string vazia, ou "0.00" (ajuste se quiser manter 0.00)
		$temDescIncond = ($vDescIncond !== null && $vDescIncond !== '' && $vDescIncond !== '0.00');
		$temDescCond   = ($vDescCond   !== null && $vDescCond   !== '' && $vDescCond   !== '0.00');

		if ($temDescIncond || $temDescCond) {
			$descontos_inner = $this->dom->createElement('vDescCondIncond');
			$valores_inner->appendChild($descontos_inner);

			$this->dom->addChild($descontos_inner, 'vDescIncond', $vDescIncond, false);
			$this->dom->addChild($descontos_inner, 'vDescCond',   $vDescCond,   false);
		}


        //TODO Fazer grupo vDedRed

        $trib_inner = $this->dom->createElement('trib');
        $valores_inner->appendChild($trib_inner);

        $tribmun_inner = $this->dom->createElement('tribMun');
        $trib_inner->appendChild($tribmun_inner);

        $this->dom->addChild(
            $tribmun_inner,
            'tribISSQN',
            $this->std->infdps->valores->trib->tribmun->tribissqn,
            true
        );

        if (isset($this->std->infdps->valores->trib->tribmun->tribissqn) && $this->std->infdps->valores->trib->tribmun->tribissqn == 
                2 && isset($this->std->infdps->valores->trib->tribmun->tpimunidade)) {
            $this->dom->addChild(
                $tribmun_inner,
                'tpImunidade',
                $this->std->infdps->valores->trib->tribmun->tpimunidade,
                true
            );
        }

        if(isset($this->std->infdps->valores->trib->tribmun->tribissqn) && $this->std->infdps->valores->trib->tribmun->tribissqn == 3){
            $this->dom->addChild(
                $tribmun_inner,
                'cPaisResult',
                $this->std->infdps->valores->trib->tribmun->cpaisresult,
                true
            );
        }

        if (isset($this->std->infdps->valores->trib->tribmun->tpretissqn)) {
            $this->dom->addChild(
                $tribmun_inner,
                'tpRetISSQN',
                $this->std->infdps->valores->trib->tribmun->tpretissqn,
                true
            );
        }

        if (isset($this->std->infdps->valores->trib->tribmun->paliq)) {
            $this->dom->addChild(
                $tribmun_inner,
                'pAliq',
                $this->std->infdps->valores->trib->tribmun->paliq,
                true
            );
        }


        if (isset($this->std->infdps->valores->trib->tribfed)) {
            $tribfed_inner = $this->dom->createElement('tribFed');
            $trib_inner->appendChild($tribfed_inner);
            if (isset($this->std->infdps->valores->trib->tribfed->piscofins)) {
                $piscofins_inner = $this->dom->createElement('piscofins');
                $tribfed_inner->appendChild($piscofins_inner);

                $this->dom->addChild(
                    $piscofins_inner,
                    'CST',
                    $this->std->infdps->valores->trib->tribfed->piscofins->cst,
                    true
                );
                if (isset($this->std->infdps->valores->trib->tribfed->piscofins->vbcpiscofins)) {
                    $this->dom->addChild(
                        $piscofins_inner,
                        'vBCPisCofins',
                        $this->std->infdps->valores->trib->tribfed->piscofins->vbcpiscofins
                    );
                }
                if (isset($this->std->infdps->valores->trib->tribfed->piscofins->paliqpis)) {
                    $this->dom->addChild(
                        $piscofins_inner,
                        'pAliqPis',
                        $this->std->infdps->valores->trib->tribfed->piscofins->paliqpis
                    );
                }
                if (isset($this->std->infdps->valores->trib->tribfed->piscofins->paliqcofins)) {
                    $this->dom->addChild(
                        $piscofins_inner,
                        'pAliqCofins',
                        $this->std->infdps->valores->trib->tribfed->piscofins->paliqcofins
                    );
                }
                if (isset($this->std->infdps->valores->trib->tribfed->piscofins->vpis)) {
                    $this->dom->addChild(
                        $piscofins_inner,
                        'vPis',
                        $this->std->infdps->valores->trib->tribfed->piscofins->vpis
                    );
                }
                if (isset($this->std->infdps->valores->trib->tribfed->piscofins->vcofins)) {
                    $this->dom->addChild(
                        $piscofins_inner,
                        'vCofins',
                        $this->std->infdps->valores->trib->tribfed->piscofins->vcofins
                    );
                }
                if (isset($this->std->infdps->valores->trib->tribfed->piscofins->tpretpiscofins)) {
                    $this->dom->addChild(
                        $piscofins_inner,
                        'tpRetPisCofins',
                        $this->std->infdps->valores->trib->tribfed->piscofins->tpretpiscofins
                    );
                }
            }
            if (isset($this->std->infdps->valores->trib->tribfed->vretcp)) {
                $this->dom->addChild(
                    $tribfed_inner,
                    'vRetCP',
                    $this->std->infdps->valores->trib->tribfed->vretcp
                );
            }
            if (isset($this->std->infdps->valores->trib->tribfed->vretirrf)) {
                $this->dom->addChild(
                    $tribfed_inner,
                    'vRetIRRF',
                    $this->std->infdps->valores->trib->tribfed->vretirrf
                );
            }
            if (isset($this->std->infdps->valores->trib->tribfed->vretcsll)) {
                $this->dom->addChild(
                    $tribfed_inner,
                    'vRetCSLL',
                    $this->std->infdps->valores->trib->tribfed->vretcsll
                );
            }
        }

        $tottrib_inner = $this->dom->createElement('totTrib');
        $trib_inner->appendChild($tottrib_inner);

        if (isset($this->std->infdps->valores->trib->tottrib->vtottrib)) {
            $vtottrib_inner = $this->dom->createElement('vTotTrib');
            $tottrib_inner->appendChild($vtottrib_inner);
            if (isset($this->std->infdps->valores->trib->tottrib->vtottrib->vtottribfed)) {
                $this->dom->addChild(
                    $vtottrib_inner,
                    'vTotTribFed',
                    $this->std->infdps->valores->trib->tottrib->vtottrib->vtottribfed
                );
            }
            if (isset($this->std->infdps->valores->trib->tottrib->vtottrib->vtottribest)) {
                $this->dom->addChild(
                    $vtottrib_inner,
                    'vTotTribEst',
                    $this->std->infdps->valores->trib->tottrib->vtottrib->vtottribest
                );
            }
            if (isset($this->std->infdps->valores->trib->tottrib->vtottrib->vtottribmun)) {
                $this->dom->addChild(
                    $vtottrib_inner,
                    'vTotTribMun',
                    $this->std->infdps->valores->trib->tottrib->vtottrib->vtottribmun
                );
            }
        }
        if (isset($this->std->infdps->valores->trib->tottrib->ptottrib)) {
            $ptottrib_inner = $this->dom->createElement('pTotTrib');
            $tottrib_inner->appendChild($ptottrib_inner);

            if (isset($this->std->infdps->valores->trib->tottrib->ptottrib->ptottribfed)) {
                $this->dom->addChild(
                    $ptottrib_inner,
                    'pTotTribFed',
                    $this->std->infdps->valores->trib->tottrib->ptottrib->ptottribfed
                );
            }
            if (isset($this->std->infdps->valores->trib->tottrib->ptottrib->ptottribest)) {
                $this->dom->addChild(
                    $ptottrib_inner,
                    'pTotTribEst',
                    $this->std->infdps->valores->trib->tottrib->ptottrib->ptottribest
                );
            }
            if (isset($this->std->infdps->valores->trib->tottrib->ptottrib->ptottribmun)) {
                $this->dom->addChild(
                    $ptottrib_inner,
                    'pTotTribMun',
                    $this->std->infdps->valores->trib->tottrib->ptottrib->ptottribmun
                );
            }
        }

        if (isset($this->std->infdps->valores->trib->tottrib->indtottrib)) {
            $this->dom->addChild(
                $tottrib_inner,
                'indTotTrib',
                $this->std->infdps->valores->trib->tottrib->indtottrib
            );
        }
        if (isset($this->std->infdps->valores->trib->tottrib->ptottribsn)) {
            $this->dom->addChild(
                $tottrib_inner,
                'pTotTribSN',
                $this->std->infdps->valores->trib->tottrib->ptottribsn
            );
        }

        //Grupos de IBS/CBS
//        if (isset($this->std->infdps->ibscbs)) {
//            $ibscbs_inner = $this->dom->createElement('IBSCBS');
//            $infdps_inner->appendChild($ibscbs_inner);
//
//            $this->dom->addChild(
//                $ibscbs_inner,
//                'finNFSe',
//                $this->std->infdps->ibscbs->finnfse,
//                true
//            );
//            $this->dom->addChild(
//                $ibscbs_inner,
//                'indFinal',
//                $this->std->infdps->ibscbs->indfinal,
//                true
//            );
//            $this->dom->addChild(
//                $ibscbs_inner,
//                'cIndOp',
//                $this->std->infdps->ibscbs->cindop,
//                true
//            );
//            if (isset($this->std->infdps->ibscbs->tpoper)) {
//                $this->dom->addChild(
//                    $ibscbs_inner,
//                    'tpOper',
//                    $this->std->infdps->ibscbs->tpoper
//                );
//            }
//
//            //TODO Fazer grupo gRefNFSe
//
//            if (isset($this->std->infdps->ibscbs->tpentegov)) {
//                $this->dom->addChild(
//                    $ibscbs_inner,
//                    'tpEnteGov',
//                    $this->std->infdps->ibscbs->tpentegov
//                );
//            }
//            $this->dom->addChild(
//                $ibscbs_inner,
//                'indDest',
//                $this->std->infdps->ibscbs->inddest,
//                true
//            );
//            if (isset($this->std->infdps->ibscbs->dest)) {
//                $ibscbs_dest_inner = $this->dom->createElement('dest');
//                $ibscbs_inner->appendChild($ibscbs_dest_inner);
//                if (isset($this->std->infdps->ibscbs->dest->cnpj)) {
//                    $this->dom->addChild(
//                        $ibscbs_dest_inner,
//                        'CNPJ',
//                        $this->std->infdps->ibscbs->dest->cnpj,
//                        true
//                    );
//                }
//                if (isset($this->std->infdps->ibscbs->dest->cpf)) {
//                    $this->dom->addChild(
//                        $ibscbs_dest_inner,
//                        'CPF',
//                        $this->std->infdps->ibscbs->dest->cpf,
//                        true
//                    );
//                }
//                if (isset($this->std->infdps->ibscbs->dest->nif)) {
//                    $this->dom->addChild(
//                        $ibscbs_dest_inner,
//                        'NIF',
//                        $this->std->infdps->ibscbs->dest->nif,
//                        true
//                    );
//                }
//                if (isset($this->std->infdps->ibscbs->dest->cnaonif)) {
//                    $this->dom->addChild(
//                        $ibscbs_dest_inner,
//                        'cNaoNIF',
//                        $this->std->infdps->ibscbs->dest->cnaonif,
//                        true
//                    );
//                }
//                $this->dom->addChild(
//                    $ibscbs_dest_inner,
//                    'xNome',
//                    $this->std->infdps->ibscbs->dest->xnome,
//                    true
//                );
//                $this->dom->addChild(
//                    $ibscbs_dest_inner,
//                    'fone',
//                    $this->std->infdps->ibscbs->dest->fone
//                );
//                $this->dom->addChild(
//                    $ibscbs_dest_inner,
//                    'email',
//                    $this->std->infdps->ibscbs->dest->email
//                );
//
//                if (isset($this->std->infdps->ibscbs->dest->end)) {
//                    $ibscbs_dest_end_inner = $this->dom->createElement('end');
//                    $ibscbs_dest_inner->appendChild($ibscbs_dest_end_inner);
//
//                    if (isset($this->std->infdps->ibscbs->dest->end->endnac)) {
//                        $ibscbs_endnac_inner = $this->dom->createElement('endNac');
//                        $ibscbs_dest_end_inner->appendChild($ibscbs_endnac_inner);
//                        $this->dom->addChild(
//                            $ibscbs_endnac_inner,
//                            'cMun',
//                            $this->std->infdps->ibscbs->dest->end->endnac->cmun,
//                            true
//                        );
//                        $this->dom->addChild(
//                            $ibscbs_endnac_inner,
//                            'CEP',
//                            $this->std->infdps->ibscbs->dest->end->endnac->cep,
//                            true
//                        );
//                    } elseif (isset($this->std->infdps->ibscbs->dest->end->endext)) {
//                        $ibscbs_endext_inner = $this->dom->createElement('endExt');
//                        $ibscbs_dest_end_inner->appendChild($ibscbs_endext_inner);
//                        $this->dom->addChild(
//                            $ibscbs_endext_inner,
//                            'cPais',
//                            $this->std->infdps->ibscbs->dest->end->endext->cpais,
//                            true
//                        );
//                        $this->dom->addChild(
//                            $ibscbs_endext_inner,
//                            'cEndPost',
//                            $this->std->infdps->ibscbs->dest->end->endext->cendpost,
//                            true
//                        );
//                        $this->dom->addChild(
//                            $ibscbs_endext_inner,
//                            'xCidade',
//                            $this->std->infdps->ibscbs->dest->end->endext->xcidade,
//                            true
//                        );
//                        $this->dom->addChild(
//                            $ibscbs_endext_inner,
//                            'xEstProvReg',
//                            $this->std->infdps->ibscbs->dest->end->endext->xestprovreg,
//                            true
//                        );
//                    }
//                    $this->dom->addChild(
//                        $ibscbs_dest_end_inner,
//                        'xLgr',
//                        $this->std->infdps->ibscbs->dest->end->xlgr,
//                        true
//                    );
//                    $this->dom->addChild(
//                        $ibscbs_dest_end_inner,
//                        'nro',
//                        $this->std->infdps->ibscbs->dest->end->nro,
//                        true
//                    );
//                    if (isset($this->std->infdps->ibscbs->dest->end->xcpl)) {
//                        $this->dom->addChild(
//                            $ibscbs_dest_end_inner,
//                            'xCpl',
//                            $this->std->infdps->ibscbs->dest->end->xcpl,
//                        );
//                    }
//                    $this->dom->addChild(
//                        $ibscbs_dest_end_inner,
//                        'xBairro',
//                        $this->std->infdps->ibscbs->dest->end->xbairro,
//                        true
//                    );
//                }
//                if (isset($this->std->ibscbs->dest->fone)) {
//                    $this->dom->addChild(
//                        $ibscbs_dest_inner,
//                        'fone',
//                        $this->std->ibscbs->dest->fone
//                    );
//                }
//                if (isset($this->std->ibscbs->dest->email)) {
//                    $this->dom->addChild(
//                        $ibscbs_dest_inner,
//                        'email',
//                        $this->std->ibscbs->dest->email
//                    );
//                }
//            }
//
//            //TODO Fazer grupo imovel
//
//            if (isset($this->std->infdps->ibscbs->valores)) {
//                $ibscbs_valores_inner = $this->dom->createElement('valores');
//                $ibscbs_inner->appendChild($ibscbs_valores_inner);
//
//                $ibscbs_valores_trib_inner = $this->dom->createElement('trib');
//                $ibscbs_valores_inner->appendChild($ibscbs_valores_trib_inner);
//
//                $ibscbs_valores_trib_gibscbs_inner = $this->dom->createElement('gIBSCBS');
//                $ibscbs_valores_trib_inner->appendChild($ibscbs_valores_trib_gibscbs_inner);
//                $this->dom->addChild(
//                    $ibscbs_valores_trib_gibscbs_inner,
//                    'CST',
//                    $this->std->infdps->ibscbs->valores->trib->gibscbs->cst,
//                    true
//                );
//                $this->dom->addChild(
//                    $ibscbs_valores_trib_gibscbs_inner,
//                    'cClassTrib',
//                    $this->std->infdps->ibscbs->valores->trib->gibscbs->cclasstrib,
//                    true
//                );
//                if (isset($this->std->infdps->ibscbs->valores->trib->gibscbs->ccredpres)) {
//                    $this->dom->addChild(
//                        $ibscbs_valores_trib_gibscbs_inner,
//                        'cCredPres',
//                        $this->std->infdps->ibscbs->valores->trib->gibscbs->ccredpres
//                    );
//                }
//
//                $ibscbs_valores_trib_gtribregular_inner = $this->dom->createElement('gTribRegular');
//                $ibscbs_valores_trib_inner->appendChild($ibscbs_valores_trib_gtribregular_inner);
//                $this->dom->addChild(
//                    $ibscbs_valores_trib_gtribregular_inner,
//                    'CSTReg',
//                    $this->std->infdps->ibscbs->valores->trib->gtribregular->cstreg,
//                    true
//                );
//                $this->dom->addChild(
//                    $ibscbs_valores_trib_gtribregular_inner,
//                    'cClassTribReg',
//                    $this->std->infdps->ibscbs->valores->trib->gtribregular->cclasstribreg,
//                    true
//                );
//
//                $ibscbs_valores_trib_gdif_inner = $this->dom->createElement('gDif');
//                $ibscbs_valores_trib_inner->appendChild($ibscbs_valores_trib_gdif_inner);
//                $this->dom->addChild(
//                    $ibscbs_valores_trib_gdif_inner,
//                    'pDifUF',
//                    $this->std->infdps->ibscbs->valores->trib->gdif->pdifuf,
//                    true
//                );
//                $this->dom->addChild(
//                    $ibscbs_valores_trib_gdif_inner,
//                    'pDifMun',
//                    $this->std->infdps->ibscbs->valores->trib->gdif->pdifmun,
//                    true
//                );
//                $this->dom->addChild(
//                    $ibscbs_valores_trib_gdif_inner,
//                    'pDifCBS',
//                    $this->std->infdps->ibscbs->valores->trib->gdif->pdifcbs,
//                    true
//                );
//
//                //TODO Fazer grupo gReeRepRes
//
//
//            }
//
//        }

        $dps = $this->dom->createElement('DPS');
        $dps->setAttribute('versao', $this->std->version);
        $dps->setAttribute('xmlns', 'http://www.sped.fazenda.gov.br/nfse');
        $this->dps->appendChild($infdps_inner);
        $this->dom->appendChild($this->dps);
        /*        return str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $this->dom->saveXML());*/
        return $this->dom->saveXML();
    }

    public function renderEvento(stdClass $std = null)
    {
        if ($this->dom->hasChildNodes()) {
            $this->dom = new Dom('1.0', 'UTF-8');
            $this->dom->preserveWhiteSpace = false;
            $this->dom->formatOutput = false;
        }

        $this->init($std);
        $this->evento = $this->dom->createElement('pedRegEvento');
        $this->evento->setAttribute('versao', $this->std->version);
        $this->evento->setAttribute('xmlns', 'http://www.sped.fazenda.gov.br/nfse');

        $infpedreg_inner = $this->dom->createElement('infPedReg');
        $infpedreg_inner->setAttribute('Id', $this->generatePre());

        $this->dom->addChild(
            $infpedreg_inner,
            'tpAmb',
            $this->std->infpedreg->tpamb,
            true
        );
        $this->dom->addChild(
            $infpedreg_inner,
            'verAplic',
            $this->std->infpedreg->veraplic,
            true
        );
        $this->dom->addChild(
            $infpedreg_inner,
            'dhEvento',
            $this->std->infpedreg->dhevento,
            true
        );
        if (isset($this->std->infpedreg->cnpjautor)) {
            $this->dom->addChild(
                $infpedreg_inner,
                'CNPJAutor',
                $this->std->infpedreg->cnpjautor,
                true
            );
        }
        if (isset($this->std->infpedreg->cpfautor)) {
            $this->dom->addChild(
                $infpedreg_inner,
                'CPFAutor',
                $this->std->infpedreg->cpfautor,
                true
            );
        }
        $this->dom->addChild(
            $infpedreg_inner,
            'chNFSe',
            $this->std->infpedreg->chnfse,
            true
        );


        if (isset($this->std->infpedreg->e101101)) {
            $e101101_inner = $this->dom->createElement('e101101');
            $infpedreg_inner->appendChild($e101101_inner);
            $this->dom->addChild(
                $e101101_inner,
                'xDesc',
                $this->std->infpedreg->e101101->xdesc,
                true
            );
            $this->dom->addChild(
                $e101101_inner,
                'cMotivo',
                $this->std->infpedreg->e101101->cmotivo,
                true
            );
            $this->dom->addChild(
                $e101101_inner,
                'xMotivo',
                $this->std->infpedreg->e101101->xmotivo,
                true
            );
        }

        $dps = $this->dom->createElement('DPS');
        $dps->setAttribute('versao', $this->std->version);
        $dps->setAttribute('xmlns', 'http://www.sped.fazenda.gov.br/nfse');
        $this->evento->appendChild($infpedreg_inner);
        $this->dom->appendChild($this->evento);
        /*        return str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $this->dom->saveXML());*/
        return $this->dom->saveXML();
    }

    public function setFormatOutput(bool $formatOutput)
    {
        $this->dom->formatOutput = $formatOutput;
    }

    public function setStd(stdClass $std)
    {
        $this->init($std);
    }

    /**
     * Mudar todas proprioedades da stdClass para minúsculas
     * @param stdClass $data
     * @return stdClass
     */
    public static function propertiesToLower(stdClass $data)
    {
        $properties = get_object_vars($data);
        $clone = new stdClass();
        foreach ($properties as $key => $value) {
            if ($value instanceof stdClass) {
                $value = self::propertiesToLower($value);
            }
            $newkey = strtolower($key);
            $clone->{$newkey} = $value;
        }
        return $clone;
    }

    //    /**
    //     * Validation json data from json Schema
    //     * @param stdClass $data
    //     * @return boolean
    //     * @throws \RuntimeException
    //     */
    //    protected function validInputData()
    //    {
    //        if (!is_file($this->jsonschema)) {
    //            return true;
    //        }
    //        $validator = new JsonValid();
    //        $validator->check($this->std, (object)['$ref' => 'file://' . $this->jsonschema]);
    //        if (!$validator->isValid()) {
    //            $msg = "";
    //            foreach ($validator->getErrors() as $error) {
    //                $msg .= sprintf("[%s] %s\n", $error['property'], $error['message']);
    //            }
    //            throw new InvalidArgumentException($msg);
    //        }
    //        return true;
    //    }

    public function getDpsId()
    {
        return $this->dpsId;
    }

    public function getEventoId()
    {
        return $this->preId;
    }

    private function generateId()
    {
        $string = 'DPS';
        $string .= substr($this->std->infdps->clocemi, 0, 7); //Cód.Mun. (7) +
        $string .= isset($this->std->infdps->prest->cnpj) ? 2 : 1; //Tipo de Inscrição Federal (1) +
        if (isset($this->std->infdps->prest->cnpj)) {
            $inscricao = $this->std->infdps->prest->cnpj;
        } else {
            $inscricao = $this->std->infdps->prest->cpf;
        }
        $string .= str_pad($inscricao, 14, 0, STR_PAD_LEFT); //Inscrição Federal (14 - CPF completar com 000 à esquerda) +
        $string .= str_pad($this->std->infdps->serie, 5, 0, STR_PAD_LEFT); //Série DPS (5) +
        $string .= str_pad($this->std->infdps->ndps, 15, 0, STR_PAD_LEFT); //Série DPS (5) +
        $this->dpsId = $string;
        return $string;
    }

    private function generatePre()
    {
        $string = 'PRE';
        $string .= $this->std->infpedreg->chnfse; //Chave de acesso da NFS-e (50) +
        $string .= $this->codigoEvento(); //Código do evento (6)
        $this->preId = $string;
        return $string;
    }

    private function codigoEvento()
    {
        $codigo = '000000';
        switch (true) {
            case isset($this->std->infpedreg->e101101):
                $codigo = '101101';
                break;
            case isset($this->std->infpedreg->e105102):
                $codigo = '105102';
                break;
        }

        return $codigo;
    }

}
