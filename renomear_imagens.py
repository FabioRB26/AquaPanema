#!/usr/bin/env python3
# renomear_lote_subpastas.py
from pathlib import Path

# ========== CONFIG ==========
# Caminho da PASTA BASE que contém as subpastas (uma por espécie)
PASTA_BASE = Path(r"C:\Users\Fábio\Desktop\Banco de Imagens Completo\original")

# Prefixo ANTIGO dos arquivos dentro de cada subpasta
ANTIGO_PREFIXO = "img"

# Intervalo de arquivos esperados (img01..img65)
INICIO = 1
FIM = 65

# Dígitos do novo padrão (001..065)
DIGITOS = 3

# Extensões aceitas (sempre preserva a extensão original)
EXTS_ACEITAS = ("jpg", "jpeg", "png")

# Separador entre prefixo (nome da pasta) e número. "" = sem separador; "_" = com underline.
SEP = ""  # exemplo: "" -> Ancistrus_albino001 | "_" -> Ancistrus_albino_001

# Modo de execução: False = teste (não renomeia), True = aplica
APLICAR = True
# ===========================

def renomear_pasta(pasta: Path):
    """Renomeia os arquivos dentro de 'pasta' usando o nome da pasta como prefixo."""
    if not pasta.is_dir():
        return 0, 0, 0

    novo_prefixo = pasta.name  # usa o nome da pasta como prefixo
    print(f"\n== Pasta: {pasta} ==")
    print(f"Novo prefixo: {novo_prefixo}")
    print(f"Antigo prefixo: {ANTIGO_PREFIXO}")
    print(f"Intervalo: {str(INICIO).zfill(DIGITOS)} até {str(FIM).zfill(DIGITOS)}")
    print(f"Extensões aceitas: {', '.join(EXTS_ACEITAS)}")
    print(f"Modo: {'APLICAR (renomeando)' if APLICAR else 'TESTE (dry-run)'}")
    print("-" * 60)

    total_ok = 0
    faltando = 0
    conflitos = 0

    for i in range(INICIO, FIM + 1):
        num_antigo = str(i).zfill(2)  # antigos são img01..img65
        num_novo = str(i).zfill(DIGITOS)  # novos são 001..065

        # Localiza o arquivo existente com qualquer extensão aceita
        candidato = None
        for ext in EXTS_ACEITAS:
            arq = pasta / f"{ANTIGO_PREFIXO}{num_antigo}.{ext}"
            if arq.exists():
                candidato = arq
                break

        if candidato is None:
            print(f"[AVISO] Não encontrado: {ANTIGO_PREFIXO}{num_antigo}.{{{','.join(EXTS_ACEITAS)}}}")
            faltando += 1
            continue

        ext_final = candidato.suffix.lower()  # mantém extensão original
        novo_nome = f"{novo_prefixo}{SEP}{num_novo}{ext_final}"
        destino = pasta / novo_nome

        if destino.exists():
            print(f"[CONFLITO] Já existe: {destino.name} (pulando)")
            conflitos += 1
            continue

        print(f"{candidato.name} -> {destino.name}")
        if APLICAR:
            candidato.rename(destino)
        total_ok += 1

    print("-" * 60)
    print(f"Sucessos (previstos ou aplicados): {total_ok}")
    print(f"Arquivos não encontrados: {faltando}")
    print(f"Conflitos: {conflitos}")
    return total_ok, faltando, conflitos


def main():
    if not PASTA_BASE.exists():
        print(f"[ERRO] Pasta base não encontrada: {PASTA_BASE}")
        return

    # Se você quiser limitar às pastas específicas, liste-as aqui; caso contrário, pega TODAS as subpastas:
    # nomes_desejados = {
    #     "Ancistrus_albino", "Astyanax_altiparanae", "Bryconamericus_iheringii", ...
    # }
    nomes_desejados = None  # use None para todas as subpastas

    subpastas = [p for p in PASTA_BASE.iterdir() if p.is_dir()]
    if nomes_desejados:
        subpastas = [p for p in subpastas if p.name in nomes_desejados]

    total_ok = faltando = conflitos = 0
    for pasta in sorted(subpastas, key=lambda x: x.name.lower()):
        ok, fal, conf = renomear_pasta(pasta)
        total_ok += ok
        faltando += fal
        conflitos += conf

    print("\n====== RESUMO GERAL ======")
    print(f"Total sucessos (previstos ou aplicados): {total_ok}")
    print(f"Total não encontrados: {faltando}")
    print(f"Total conflitos: {conflitos}")
    if not APLICAR:
        print("\n[NOTA] Nada foi renomeado (modo teste). Defina APLICAR = True para executar.")

if __name__ == "__main__":
    main()
