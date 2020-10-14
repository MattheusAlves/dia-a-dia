<?php

$query_CC = <<<QUERY
	SELECT 
    cirg.situacao,
    natureza_agend as "Natureza do Agendamento",
    --	dthr_inicio_cirg,
    --	dthr_fim_cirg,
    --	dthr_inicio_ordem,
    case
		when situacao = 'CANC' then  'Nao Realizadas'
		WHEN CIRG.dthr_inicio_cirg < :dataf then 'Realizadas'
		else 'Nao Realizadas'
	end ind_realizada,
	count(*)  as "Num de Cirurgias no Dia"
	FROM
        agh.v_lista_mbc_cirurgias cirg
    WHERE cirg.dthr_inicio_cirg >= :datai AND  cirg.dthr_inicio_cirg < :dataf2
    and unf_seq = :unf
    GROUP BY cirg.situacao,natureza_agend,ind_realizada
    ORDER BY cirg.situacao,natureza_agend,ind_realizada
QUERY;

$query_CCAgendadas = <<<QUERY
select
 	count(*) as "Total"
 	from agh.mbc_agendas  agen
  	inner join agh.mbc_cirurgias cir on agen.seq = cir.agd_seq 
	where cir.dthr_prev_inicio >= :datai and cir.dthr_prev_inicio < :dataf and cir.unf_seq = :unf
QUERY;

$query_media_diaria =  <<<QUERY
	SELECT
		situacao,
		natureza_agend as "Natureza do Agendamento",	
		round((count(*)::FLOAT / 365)) as "Media Diaria do Ano Anterior"
		FROM
			agh.v_lista_mbc_cirurgias cirg
		WHERE cirg.dthr_inicio_cirg >= :datai AND  cirg.dthr_inicio_cirg <=  :dataf
		and unf_seq = :unf
		GROUP BY cirg.situacao,natureza_agend
		ORDER BY cirg.situacao,natureza_agend
QUERY;

$query_cirg_porte = <<<QUERY
select 
--	age(dthr_fim_cirg,dthr_inicio_cirg) as Intervalo,
--	lpad(extract(month from dthr_inicio_cirg)||'/'||extract(year from dthr_inicio_cirg), 7, '0') as "Mes",
	case 
		when age(dthr_fim_cirg,dthr_inicio_cirg) <='01:00:00' Then '1. Pequeno'
		when age(dthr_fim_cirg,dthr_inicio_cirg) between '01:00:01' and '02:00:00' Then '2. Medio'
		else '3. Grande'
	end  as "porte", 
	count(*) as "Total por Porte",
	to_char(
		(count(*)::float/
		(select count(*) from agh.v_lista_mbc_cirurgias where dthr_inicio_cirg >= :datai
		 and dthr_inicio_cirg < :dataf and unf_seq = :unf and situacao != 'CANC')::float
		)*100, 
	'999D99%') as percentual
--	* 
from 
	agh.v_lista_mbc_cirurgias 
where 
	dthr_inicio_cirg >= :datai and dthr_inicio_cirg < :dataf
	and unf_seq = :unf and situacao != 'CANC'

group by porte 
Order by porte


QUERY;
$query_cirg_porte_media = <<<QUERY
select 
--	age(dthr_fim_cirg,dthr_inicio_cirg) as Intervalo,
--	lpad(extract(month from dthr_inicio_cirg)||'/'||extract(year from dthr_inicio_cirg), 7, '0') as "Mes",
	case 
		when age(dthr_fim_cirg,dthr_inicio_cirg) <='01:00:00' Then '1. Pequeno'
		when age(dthr_fim_cirg,dthr_inicio_cirg) between '01:00:01' and '02:00:00' Then '2. Medio'
		else '3. Grande'
	end  as "porte", 
	round(count(*)::FLOAT / 365) as "Total por Porte(arredondado)",
	to_char(
		(count(*)::float/
		(select count(*) from agh.v_lista_mbc_cirurgias where dthr_inicio_cirg >= :datai
		 and dthr_inicio_cirg < :dataf and unf_seq = :unf and situacao != 'CANC')::float
		)*100, 
	'999D99%') as percentual
--	* 
from 
	agh.v_lista_mbc_cirurgias 
where 
	dthr_inicio_cirg >= :datai and dthr_inicio_cirg < :dataf
	and unf_seq = :unf and situacao != 'CANC'

group by porte 
Order by porte


QUERY;

$query_Enfermarias_old = <<<QUERY
	select pq.unf_Seq as "Unidade Funcional", 
	alas.descricao,
		case
			when tml_DESCRICAO = 'INFECÇÃO' THEN 'BLOQUEADO'
			WHEN tml_descricao= 'BLOQUEIO ADMINISTRATIVO' then 'BLOQUEADO'
			WHEN tml_descricao = 'DESOCUPADO' AND LIVRE = 'LIVRE' THEN 'LIVRE'
			WHEN TML_DESCRICAO = 'BLOQUEIO LIMPEZA' THEN 'BLOQUEIO LIMPEZA'
			WHEN TML_DESCRICAO = 'LIMPEZA' THEN 'BLOQUEIO LIMPEZA'
			WHEN PQ.grupo_mvto_leito ='O' THEN 'OCUPADO'
			else el.tml_descricao
			END AS "situacao",
			COUNT(*) as "Quantidade"
			from agh.v_ain_pesq_leitos pq left join agh.v_ain_extrato_leitos el on
			pq.lto_lto_id = el.lto_lto_id and el.dthr_lancamento = pq.dthr_lancamento
			inner join agh.agh_alas alas on pq.ind_ala = alas.codigo
	group by pq.unf_seq, alas.descricao,"situacao"
	order by unf_seq
QUERY;
$query_Enfermarias = <<<QUERY
SELECT le.unf_seq::text as "Unidade Funcional",
		 uf.descricao,
			uf.ind_ala as "Ala",
		case
				WHEN tml_codigo in(24,25,22) 			then 'DESATIVADO'
				WHEN tml_codigo in (0,21,23,29,31,30)	THEN 'LIVRE'
				WHEN tml_codigo = 16 				THEN 'OCUPADO'
				eLSE tmvto.descricao
		END AS "situacao", COUNT(*) as "Quantidade"
		FROM AGH.AIN_LEITOS LE LEFT JOIN AGH.AGH_UNIDADES_FUNCIONAIS UF ON 
		LE.UNF_SEQ = UF.SEQ 
		left join agh.ain_tipos_mvto_leito tmvto on le.tml_codigo = tmvto.codigo
		where ind_situacao = 'A'
	GROUP BY LE.UNF_SEQ,uf.descricao,uf.ind_ala,"situacao"
	order by uf.descricao,"situacao"
QUERY;

$query_EnfermariasInternacoes = <<<QUERY
select 
count(*)  as "Atendimento diario",
	(SELECT to_char(round((count(*)::FLOAT / 365 )),'FM999999990')  FROM  agh.ain_internacoes
	where 
	dthr_internacao  >= :dataiMedia and dthr_internacao < :datafMedia ) AS "Media diaria(internacoes do ano anterior)",
	(select 
	count(*) 
	from   agh.ain_internacoes  inte
	inner join agh.aip_pacientes pa 
	on pa.codigo = inte.pac_codigo 
	where age(pa.dt_obito,inte.dthr_internacao) <=  '24:00:00' 
	and dthr_internacao  >= :datai2 and dthr_internacao < :dataf2 ) as "Obitos em ate 24h",

	(select 
	to_char(round((count(*)::FLOAT / 365 )),'FM999999990')
	from  agh.ain_internacoes  inte
	inner join agh.aip_pacientes pa 
	on pa.codigo = inte.pac_codigo 
	where age(pa.dt_obito,inte.dthr_internacao) <=  '24:00:00' 
	and dthr_internacao  >= :dataiMedia and dthr_internacao < :datafMedia ) as "Obitos em ate 24h(Media diaria do ano anterior)",

	(SELECT count(*) FROM agh.ain_internacoes
	where dthr_alta_medica >= :datai and dthr_alta_medica < :dataf ) as "Altas do dia",
	
	(SELECT to_char(round((count(*)::FLOAT / 365) ),'FM999999990') from agh.ain_internacoes
	WHERE dthr_alta_medica  BETWEEN :dataiMedia and :datafMedia ) as "Altas(media diaria do ano anterior)",
	
	(select count(*) from agh.ain_internacoes  inte
	inner join agh.aip_pacientes pa 
	on pa.codigo = inte.pac_codigo 
	where age(pa.dt_obito,inte.dthr_internacao) >  '24:00:00' 
	and dthr_internacao  >= :datai and dthr_internacao < :dataf) as "Obitos após 24h",

	(select to_char(round((count(*)::FLOAT / 365 )),'FM999999990') from agh.ain_internacoes  inte
	inner join agh.aip_pacientes pa 
	on pa.codigo = inte.pac_codigo 
	where age(pa.dt_obito,inte.dthr_internacao) >  '24:00:00' 
	and dthr_internacao  >= :dataiMedia and dthr_internacao < :datafMedia) as "Obitos após 24h(Media diaria do ano anterior)"
	from 
	  agh.ain_internacoes  inte
		inner join agh.aip_pacientes pa 
		on pa.codigo = inte.pac_codigo
	where 
	dthr_internacao  >= :datai and dthr_internacao < :dataf
QUERY;

$query_ExamesRadiologia = <<<QUERY
select
	case 
		--wheN ufe_unf_seq = 22 then 'LABORATÓRIO DE ANÁLISES CLÍNICAS'
		when ufe_unf_seq = 80 then 'RADIOLOGIA'
		--ELSE 'IMAGEM'
	END AS "DESCRIÇÃO",
	case
		when ufe_unf_seq = 80 and LEFT(descricao_usual::TEXT,5) = 'TOMOG' then 'TOMOGRAFIA'
		when ufe_unf_seq = 80 and LEFT(descricao_usual::TEXT,2) = 'TC' then 'TOMOGRAFIA'
		when ufe_unf_seq = 80 and LEFT(descricao_usual::TEXT,9) = 'ANGIOTOMO' then 'TOMOGRAFIA'
		when ufe_unf_seq = 80 THEN 'RX'
	--	when ufe_unf_seq = 22 then 'LAB'
	end as "Exame",
	count(*) as "Quantidade"
	 from agh.v_ael_item_solic_exames item
	 inner join AGH.AEL_EXAMES sg on  sg.sigla = item.ufe_ema_exa_sigla
	 where ITEM.dthr_programada >= :datai and ITEM.dthr_programada < :dataf 
	 and ufe_unf_seq != 20 and ufe_unf_seq != 22
	group by "DESCRIÇÃO","Exame"
	order by "DESCRIÇÃO"
QUERY;

$query_ExamesRadiologiaMedia = <<<QUERY
select
	case 
		--wheN ufe_unf_seq = 22 then 'LABORATÓRIO DE ANÁLISES CLÍNICAS'
		when ufe_unf_seq = 80 then 'RADIOLOGIA'
	--	ELSE 'IMAGEM'
	END AS "DESCRIÇÃO",
	case
		when ufe_unf_seq = 80 and LEFT(descricao_usual::TEXT,5) = 'TOMOG' then 'TOMOGRAFIA'
		when ufe_unf_seq = 80 and LEFT(descricao_usual::TEXT,2) = 'TC' then 'TOMOGRAFIA'
		when ufe_unf_seq = 80 and LEFT(descricao_usual::TEXT,9) = 'ANGIOTOMO' then 'TOMOGRAFIA'
		when ufe_unf_seq = 80 THEN 'RX'
	--	when ufe_unf_seq = 22 then 'LAB'
	end as "Exame",
	to_char(round(count(*)::FLOAT/ 365),'FM999999990') as "Média diária do Ano Anterior"
	 from agh.v_ael_item_solic_exames item
	 inner join AGH.AEL_EXAMES sg on  sg.sigla = item.ufe_ema_exa_sigla
	 where ITEM.dthr_programada >= :datai and ITEM.dthr_programada < :dataf  
	 and ufe_unf_seq != 20 AND ufe_unf_seq != 22
	group by "DESCRIÇÃO","Exame"
	order by "DESCRIÇÃO"
QUERY;

$queryp_itemsId = <<<QUERY
SELECT rotulo,iditem from informacao.item where rotulo like 'CIRUR%'
QUERY;
$query_ConsultasAmbulatoriais_Old =<<<QUERY
SELECT 
	case 
		when usl_unf_Seq = 77 THEN 'Oncologia'
		when usl_unf_seq = 75 THEN 'Ambulatorio'
	end as "Unidade",
	count(*) as "Quantidade" 
	from agh.aac_consultas con inner join agh.aac_grade_agendamen_consultas ag ON
	con.grd_seq = ag.seq where dthr_inicio >= :datai and dthr_fim < :dataf and
	(usl_unf_seq = 75 OR usl_unf_seq = 77)
	grouP BY "Unidade"
QUERY;
$query_ConsultasAmbulatoriaisMedia_Old =<<<QUERY
SELECT 
	case 
		when usl_unf_Seq = 77 THEN 'Oncologia'
		when usl_unf_seq = 75 THEN 'Ambulatorio'
	end as "Unidade",
	to_char(round(count(*)::FLOAT/ 365),'FM999999990') as "Média diária do ano Anterior"
	from agh.aac_consultas con inner join agh.aac_grade_agendamen_consultas ag ON
	con.grd_seq = ag.seq where dthr_inicio >= :dataiMedia and dthr_fim < :datafMedia and
	(usl_unf_seq = 75 OR usl_unf_seq = 77)
	grouP BY "Unidade"
QUERY;
$query_ConsultasAmbulatoriais = <<<QUERY
	SELECT  atd.unf_seq as "Codigo Unidade", 
	case 
		WHEN atd.unf_seq = 75 THEN 'Ambulatorio'
		when atd.unf_seq = 77 then 'Oncologia'
		else atd.unf_seq::text
	end as "Unidade",
	--con.numero    as numero_consulta, 
	--con.dthr_inicio,con.dthr_fim, iph.cod_tabela  as Numero_SUS,
	count(*) as "Quantidade",
	iph.descricao	AS "Procedimento SUS"
	--compl1.valor AS CBO, COALESCE(cph.quantidade, 1) AS	Quantidade,
	--   unf
	--cph.phi_Seq
	FROM   agh.aac_consultas con inner JOIN agh.aac_consulta_proced_hospitalar cph    ON con.numero = cph.con_numero     
	inner JOIN agh.fat_proced_hosp_internos phi ON	cph.phi_seq = phi.seq 
	inner JOIN agh.fat_conv_grupo_itens_proced cgp ON phi.seq = cgp.phi_seq 
	inner JOIN agh.fat_itens_proced_hospitalar iph	ON cgp.iph_seq = iph.seq
	JOIN agh.aac_retornos ret ON ret.seq = con.ret_seq 
	JOIN agh.agh_atendimentos atd ON atd.con_numero = con.numero
	JOIN	agh.agh_unidades_funcionais unf ON unf.seq = atd.unf_seq 
	JOIN agh.aac_grade_agendamen_consultas agd ON agd.seq =	con.grd_seq    
	JOIN agh.agh_especialidades esp  ON esp.seq = agd.esp_seq
	inner	join  agh.rap_servidores ser2   on    agd.pre_ser_matricula =	ser2.matricula and agd.pre_ser_vin_codigo = ser2.vin_codigo 
	inner	join  agh.rap_pessoa_tipo_informacoes compl1 on    compl1.pes_codigo = ser2.pes_codigo and    compl1.dt_fim is null and compl1.tii_seq in (2,3,4,5,6)
	inner join agh.fat_procedimentos_cbo  fpcbo    on    fpcbo.iph_pho_seq	= iph.pho_seq and  fpcbo.iph_seq = iph.seq -- and fpcbo.dt_fim is null inner
	join agh.fat_cbos  fcbo    on fcbo.seq = fpcbo.cbo_seq and fcbo.codigo =substr(compl1.valor,1,6)  
	WHERE con.dt_consulta >= :datai
	AND con.dt_consulta < :dataf and ret.seq = 10 
	and atd.unf_seq in (77,75) and cph.phi_seq in (400021, 400041)
	group by atd.unf_seq,"Unidade","Procedimento SUS"
	order by "Unidade","Procedimento SUS"
QUERY;

$query_ConsultasAmbulatoriaisMedia = <<<QUERY
SELECT  atd.unf_seq as "Codigo Unidade", 
	case 
		WHEN atd.unf_seq = 75 THEN 'Ambulatorio'
		when atd.unf_seq = 77 then 'Oncologia'
		else atd.unf_seq::text
	end as "Unidade",
	--con.numero    as numero_consulta, 
	--con.dthr_inicio,con.dthr_fim, iph.cod_tabela  as Numero_SUS,
	to_char(round(count(*)::FLOAT/ 365),'FM999999990') as "Média diária do ano Anterior",
	iph.descricao	AS "Procedimento SUS"
	--compl1.valor AS CBO, COALESCE(cph.quantidade, 1) AS	Quantidade,
	--   unf
	--cph.phi_Seq
	FROM   agh.aac_consultas con inner JOIN agh.aac_consulta_proced_hospitalar cph    ON con.numero = cph.con_numero     
	inner JOIN agh.fat_proced_hosp_internos phi ON	cph.phi_seq = phi.seq 
	inner JOIN agh.fat_conv_grupo_itens_proced cgp ON phi.seq = cgp.phi_seq 
	inner JOIN agh.fat_itens_proced_hospitalar iph	ON cgp.iph_seq = iph.seq
	JOIN agh.aac_retornos ret ON ret.seq = con.ret_seq 
	JOIN agh.agh_atendimentos atd ON atd.con_numero = con.numero
	JOIN	agh.agh_unidades_funcionais unf ON unf.seq = atd.unf_seq 
	JOIN agh.aac_grade_agendamen_consultas agd ON agd.seq =	con.grd_seq    
	JOIN agh.agh_especialidades esp  ON esp.seq = agd.esp_seq
	inner	join  agh.rap_servidores ser2   on    agd.pre_ser_matricula =	ser2.matricula and agd.pre_ser_vin_codigo = ser2.vin_codigo 
	inner	join  agh.rap_pessoa_tipo_informacoes compl1 on    compl1.pes_codigo = ser2.pes_codigo and    compl1.dt_fim is null and compl1.tii_seq in (2,3,4,5,6)
	inner join agh.fat_procedimentos_cbo  fpcbo    on    fpcbo.iph_pho_seq	= iph.pho_seq and  fpcbo.iph_seq = iph.seq -- and fpcbo.dt_fim is null inner
	join agh.fat_cbos  fcbo    on fcbo.seq = fpcbo.cbo_seq and fcbo.codigo =substr(compl1.valor,1,6)  
	WHERE con.dt_consulta >= :dataiMedia
	AND con.dt_consulta < :datafMedia and ret.seq = 10 
	and atd.unf_seq in (77,75) and cph.phi_seq in (400021, 400041)
	group by atd.unf_seq,"Unidade","Procedimento SUS"
	order by "Unidade","Procedimento SUS"

QUERY;

$query_ConsultasPSAPSI = <<<QUERY
SELECT
	case
		WHEN atd.unf_seq = 61 then 'EMERGENCIA PRONTO SOCORRO ADULTO'
		when atd.unf_seq = 126 THEN 'EMERGENCIA PRONTO SOCORRO INFANTIL'
	END AS "Unidade Funcional",
        count(con.numero) as "Total"
    FROM
        agh.aac_consultas con
        INNER JOIN agh.agh_atendimentos atd
        on atd.con_numero = con.numero
    WHERE
        atd.unf_seq in (61,126)     ---- 126 = Pronto Socorro Infantil
     --   AND con.grd_seq = 566      ---- Grade do Pronto Socorro Infantil
        AND con.dt_consulta between :datai and :dataf ------ Periodo da Alta
	group by atd.unf_seq
	order by "Unidade Funcional"
QUERY;


// // $queryp_CCUpdate = "UPDATE INFORMACAO.DIAADIA SET VALOR = 35 WHERE idsubitem = 8 and datalanc = $data";
