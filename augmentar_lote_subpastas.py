#!/usr/bin/env python3
# augmentar_lote_subpastas.py (sem NumPy, com resize 512x512)
from pathlib import Path
import random
import math
from PIL import Image, ImageEnhance, ImageFilter, ImageOps

# ========== CONFIG ==========
PASTA_BASE = Path(r"C:\Users\Fábio\Desktop\Banco de Imagens Completo\original")

ALVO_QTD = 500
DIGITOS = 3
EXT_SAIDA = ".jpg"
QUALIDADE_JPG = 90

EXTS_IN = (".jpg", ".jpeg", ".png")

APLICAR = True

# Todas as imagens serão padronizadas para este tamanho:
TARGET_W, TARGET_H = 512, 512

# Limiar mínimo (por segurança) — já vamos padronizar para 512, então raramente usará.
MIN_W, MIN_H = 128, 128
# ===========================


def listar_imagens_base(pasta: Path):
    return [p for p in sorted(pasta.iterdir())
            if p.is_file() and p.suffix.lower() in EXTS_IN]


def next_indices_faltantes(pasta: Path, prefixo: str, alvo_qtd: int, digitos: int, ext_saida: str):
    faltantes = []
    for i in range(1, alvo_qtd + 1):
        nome = f"{prefixo}{str(i).zfill(digitos)}{ext_saida}"
        if not (pasta / nome).exists():
            faltantes.append(i)
    return faltantes


def rand_bool(p=0.5):
    return random.random() < p


def random_affine_params(max_rotate=25, max_shear=0.2, max_translate=0.08, max_scale_delta=0.12):
    angle = random.uniform(-max_rotate, max_rotate)
    shear = random.uniform(-max_shear, max_shear)  # ~tangente
    tx = random.uniform(-max_translate, max_translate)
    ty = random.uniform(-max_translate, max_translate)
    scale = 1.0 + random.uniform(-max_scale_delta, max_scale_delta)
    return angle, shear, tx, ty, scale


def apply_affine(img: Image.Image):
    """Aplica rotação, shear, translate e scale, retornando no mesmo tamanho."""
    w, h = img.size  # aqui será 512x512 (padronizado)
    angle, shear, tx, ty, scale = random_affine_params()

    out = img.rotate(angle, resample=Image.BICUBIC, expand=True)

    sw, sh = out.size
    cx, cy = sw / 2, sh / 2

    shear_x = shear
    shear_y = random.uniform(-abs(shear), abs(shear))

    a = scale
    b = math.tan(shear_x)
    d = math.tan(shear_y)
    e = scale

    trans_x = tx * sw
    trans_y = ty * sh

    c = cx - a * cx - b * cy + trans_x
    f = cy - d * cx - e * cy + trans_y

    out = out.transform(
        (sw, sh),
        Image.AFFINE,
        (a, b, c, d, e, f),
        resample=Image.BICUBIC,
        fillcolor=None
    )

    # Volta para o tamanho padrão (512x512)
    out = ImageOps.fit(out, (w, h), method=Image.BICUBIC, bleed=0.0, centering=(0.5, 0.5))
    return out


def random_crop_zoom(img: Image.Image, max_crop=0.12):
    """Aplica um pequeno crop aleatório e redimensiona de volta ao tamanho padrão."""
    w, h = img.size
    dx = int(random.uniform(0, max_crop) * w)
    dy = int(random.uniform(0, max_crop) * h)
    left = random.randint(0, dx)
    top = random.randint(0, dy)
    right = w - (dx - left)
    bottom = h - (dy - top)
    if right - left < MIN_W or bottom - top < MIN_H:
        return img
    crop = img.crop((left, top, right, bottom))
    return crop.resize((w, h), Image.BICUBIC)


def augment_image(img: Image.Image):
    """Pipeline de augment sem ruído gaussiano, com padronização 512x512."""
    img = ImageOps.exif_transpose(img).convert("RGB")

    # Padroniza o tamanho logo no começo
    img = ImageOps.fit(img, (TARGET_W, TARGET_H), method=Image.BICUBIC, centering=(0.5, 0.5))

    # Flips
    if rand_bool(0.5):
        img = ImageOps.mirror(img)
    if rand_bool(0.2):
        img = ImageOps.flip(img)

    # Affine
    if rand_bool(0.9):
        img = apply_affine(img)

    # Crop/zoom leve
    if rand_bool(0.7):
        img = random_crop_zoom(img, max_crop=0.12)

    # Ajustes fotométricos
    if rand_bool(0.9):
        img = ImageEnhance.Brightness(img).enhance(random.uniform(0.7, 1.35))
    if rand_bool(0.8):
        img = ImageEnhance.Contrast(img).enhance(random.uniform(0.8, 1.3))
    if rand_bool(0.6):
        img = ImageEnhance.Color(img).enhance(random.uniform(0.75, 1.35))
    if rand_bool(0.6):
        img = ImageEnhance.Sharpness(img).enhance(random.uniform(0.75, 1.4))

    # Blur leve opcional (mantém variedade sem ficar pesado)
    if rand_bool(0.2):
        img = img.filter(ImageFilter.GaussianBlur(radius=random.uniform(0.3, 1.0)))

    return img


def salvar_jpg(im: Image.Image, destino: Path):
    destino.parent.mkdir(parents=True, exist_ok=True)
    im.save(
        destino,
        format="JPEG",
        quality=QUALIDADE_JPG,
        optimize=True,
        subsampling="4:2:0",
    )


def processar_pasta(pasta: Path):
    if not pasta.is_dir():
        return (0, 0, 0)

    prefixo = pasta.name  # sem "_"
    fontes = listar_imagens_base(pasta)
    if not fontes:
        print(f"[AVISO] Sem imagens base em: {pasta}")
        return (0, 0, 0)

    faltantes = next_indices_faltantes(pasta, prefixo, ALVO_QTD, DIGITOS, EXT_SAIDA)
    if not faltantes:
        print(f"[OK] {pasta.name}: já tem {ALVO_QTD} imagens ou mais.")
        return (0, 0, 0)

    print(f"\n== Pasta: {pasta} ==")
    print(f"Base: {len(fontes)} | Faltam: {len(faltantes)} (até {ALVO_QTD}) | Tamanho: {TARGET_W}x{TARGET_H}")
    print(f"Saída: {prefixo}XXX{EXT_SAIDA} | Modo: {'APLICAR' if APLICAR else 'TESTE'}")
    print("-" * 60)

    geradas = puladas = erros = 0
    idx_fonte = 0
    for i in faltantes:
        base_path = fontes[idx_fonte % len(fontes)]
        idx_fonte += 1

        try:
            with Image.open(base_path) as img:
                aug = augment_image(img)
                nome = f"{prefixo}{str(i).zfill(DIGITOS)}{EXT_SAIDA}"
                destino = pasta / nome
                if destino.exists():
                    print(f"[PULAR] Já existe {destino.name}")
                    puladas += 1
                    continue
                print(f"[GERAR] {destino.name}   (base: {base_path.name})")
                if APLICAR:
                    salvar_jpg(aug, destino)
                geradas += 1
        except Exception as e:
            print(f"[ERRO] {base_path.name} -> {i:0{DIGITOS}d}: {e}")
            erros += 1

    print("-" * 60)
    print(f"Resumo {pasta.name}: geradas={geradas}, puladas={puladas}, erros={erros}")
    return (geradas, puladas, erros)


def main():
    if not PASTA_BASE.exists():
        print(f"[ERRO] Pasta base não encontrada: {PASTA_BASE}")
        return

    subpastas = [p for p in sorted(PASTA_BASE.iterdir()) if p.is_dir()]
    total_g = total_p = total_e = 0
    for pasta in subpastas:
        g, p, e = processar_pasta(pasta)
        total_g += g
        total_p += p
        total_e += e

    print("\n====== RESUMO GERAL ======")
    print(f"Geradas: {total_g} | Puladas: {total_p} | Erros: {total_e}")
    if not APLICAR:
        print("\n[NOTA] Nada foi salvo (modo TESTE). Defina APLICAR = True para executar.")


if __name__ == "__main__":
    # random.seed(42)  # Descomente se quiser reprodutível
    main()
