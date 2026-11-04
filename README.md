# Supercontribuidor IA — Diagnóstico de Maturidade e Prontidão

Aplicação web interativa para avaliação do índice de prontidão profissional para Inteligência Artificial em 6 dimensões fundamentais:
1. **Contexto & Alinhamento**
2. **Conhecimento & Pesquisa**
3. **Raciocínio & Estrutura**
4. **Criação & Execução com IA**
5. **Qualidade & Rigor Humano**
6. **Impacto & Influência**

---

## 🚀 Arquitetura e Deploy na Hostinger

Esta aplicação foi desenvolvida para rodar diretamente em hospedagem web (Hostinger, cPanel ou Apache/Nginx com suporte a PHP 7.4+).

### Estrutura de Arquivos:
- `index.html`: Landing page e diagnóstico completo em Português.
- `en/index.html`: Versão completa em Inglês.
- `api/salvar.php`: Endpoint nativo em PHP para capturar e persistir os leads e diagnósticos (sem dependência de serviços externos como Make ou Zapier).
- `api/ver-leads.php`: Painel administrativo protegido por token para visualização de leads e exportação em planilha CSV.
- `data/`: Diretório de armazenamento seguro (bloqueado contra downloads externos via `.htaccess`).

---

## 🔒 Painel de Leads & Exportação CSV

Para acessar o painel de leads capturados no seu domínio:
`https://seudominio.com.br/api/ver-leads.php?token=SCONTRIB2025`

No painel, você pode:
- Ver a lista de leads em tempo real (dados de contato, pontuação e nível).
- Clicar em **"Exportar para CSV"** para baixar a planilha formatada em Excel/Google Sheets.

*(Para alterar a senha/token, edite a variável `$tokenConfig` em `api/ver-leads.php`)*.

---

## 📩 Notificações por E-mail (Opcional)

Por padrão, `api/salvar.php` envia um e-mail com o resumo do diagnóstico para `lucaslobato17@gmail.com` sempre que um usuário conclui o teste.
Para alterar ou desativar, modifique a variável `$adminEmail` no arquivo `api/salvar.php`.
