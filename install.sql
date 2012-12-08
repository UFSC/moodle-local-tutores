CREATE TABLE IF NOT EXISTS `GruposTutoria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `curso` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PessoasGruposTutoria` (
  `grupo` int(11) NOT NULL AUTO_INCREMENT,
  `matricula` varchar(11) NOT NULL,
  PRIMARY KEY (`grupo`,`matricula`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `middleware_unificado`.`PessoasGruposTutoria` ADD COLUMN `tipo` CHAR NULL  AFTER `matricula` 
, ADD INDEX `pessoas_tipo` (`grupo` ASC, `tipo` ASC) ;

