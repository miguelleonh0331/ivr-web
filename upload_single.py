#!/usr/bin/env python3
"""Subir un solo archivo a legacy usando streaming manual (evita E015)."""
import sys
import os
sys.path.insert(0, r'C:\Users\Diego\Documents\herramientas\codex multi')

import paramiko

def upload_single(local_path, remote_path):
    server = {
        'host': '192.175.22.83',
        'port': 22,
        'user': 'root',
        'key_file': r'C:\Users\Diego\.ssh\id_joyeria_legacy',
    }
    
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(
        hostname=server['host'],
        port=server['port'],
        username=server['user'],
        key_filename=server['key_file'],
        timeout=10
    )
    
    sftp = ssh.open_sftp()
    
    # Streaming manual: abrir remoto, leer local en chunks, escribir
    with open(local_path, 'rb') as f_local:
        with sftp.file(remote_path, 'wb') as f_remote:
            f_remote.set_pipelined(True)
            while True:
                chunk = f_local.read(32768)
                if not chunk:
                    break
                f_remote.write(chunk)
    
    # Verificar tamaño
    local_size = os.path.getsize(local_path)
    remote_size = sftp.stat(remote_path).st_size
    
    sftp.close()
    ssh.close()
    
    if local_size == remote_size:
        print(f"[OK] Subido: {local_path} -> {remote_path} ({local_size} bytes)")
        return True
    else:
        print(f"[ERROR] Tamaño no coincide: local={local_size}, remote={remote_size}")
        return False

if __name__ == '__main__':
    if len(sys.argv) != 3:
        print("Uso: python upload_single.py <local> <remote>")
        sys.exit(1)
    
    local = sys.argv[1]
    remote = sys.argv[2]
    
    # Convertir rutas Windows
    local = local.replace('/', '\\')
    
    success = upload_single(local, remote)
    sys.exit(0 if success else 1)
