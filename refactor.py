import os
import shutil
import re

base_dir = "/mnt/2AA67636A676031D/Pertamina/PRIMA"

file_mapping = {
    'assets': [r'.*\.css$', r'.*\.js$'],
    'config': [r'config\.php', r'config\.production\.php', r'default\.php', r'load\.php', r'system-settings\.php'],
    'docs': [r'.*\.md$'],
    'scripts': [r'.*\.bat$', r'.*\.sql$', r'_run_migration\.php', r'email-notifikasi\.php'],
    'tests': [r'test-.*', r'debug-.*', r'diagnose-.*'],
    'api': [r'api-.*', r'get\.php', r'save\.php', r'delete\.php', r'list\.php', r'export\.php', r'get-user\.php', r'get-vehicles\.php'],
    'auth': [r'auth\.php', r'login\.php', r'process-login\.php', r'register\.php', r'process-register\.php', r'logout\.php', r'fix-password\.php', r'process-reset-password\.php'],
    'admin': [r'admin-dashboard\.php', r'manage-users\.php', r'audit-logs\.php', r'approve-registrations\.php', r'process-approve\.php', r'pending-approval\.php', r'dokumen-admin\.php', r'process-edit-user\.php', r'process-toggle-user\.php'],
    'vehicles': [r'kelola-kendaraan\.php', r'register-vehicle\.php', r'vehicle-alerts\.php', r'migrate-vehicles\.php'],
    'documents': [r'upload-dokumen\.php', r'sign-checklist\.php', r'save-signature\.php', r'verify-ttd\.php', r'generate-keys\.php']
}

file_destinations = {}
files = [f for f in os.listdir(base_dir) if os.path.isfile(os.path.join(base_dir, f)) and f != 'refactor.py']

for f in files:
    matched = False
    for folder, patterns in file_mapping.items():
        for p in patterns:
            if re.match(p, f):
                file_destinations[f] = folder
                matched = True
                break
        if matched:
            break

for folder in file_mapping.keys():
    os.makedirs(os.path.join(base_dir, folder), exist_ok=True)

for f, folder in file_destinations.items():
    src = os.path.join(base_dir, f)
    dst = os.path.join(base_dir, folder, f)
    if os.path.exists(src):
        shutil.move(src, dst)

def get_depth(path):
    if not path or path == '.' or path == '': return 0
    return len(path.strip('/').split('/'))

def fix_content(content, current_folder):
    depth = get_depth(current_folder)
    prefix = '../' * depth if depth > 0 else ''
    
    def repl_php(m):
        cmd = m.group(1)
        path = m.group(2)
        basename = os.path.basename(path)
        
        if path.startswith('includes/'):
            return f"{cmd} '{prefix}{path}';"
        
        if basename in file_destinations:
            dest_folder = file_destinations[basename]
            return f"{cmd} '{prefix}{dest_folder}/{basename}';"
        elif basename in files:
            return f"{cmd} '{prefix}{basename}';"
        else:
            return m.group(0)
            
    content = re.sub(r'(include|require|include_once|require_once)\s*[\(]?\s*[\'"]([^\'"]+)[\'"]\s*[\)]?\s*;', repl_php, content)
    
    def repl_php_dir(m):
        cmd = m.group(1)
        path = m.group(2)
        path = path.lstrip('/')
        basename = os.path.basename(path)
        
        if basename in file_destinations:
            dest_folder = file_destinations[basename]
            return f"{cmd} __DIR__ . '/{prefix}{dest_folder}/{basename}';"
        elif basename in files:
            return f"{cmd} __DIR__ . '/{prefix}{basename}';"
        else:
            return m.group(0)

    content = re.sub(r'(include|require|include_once|require_once)\s*[\(]?\s*__DIR__\s*\.\s*[\'"]/?([^\'"]+)[\'"]\s*[\)]?\s*;', repl_php_dir, content)
    
    def repl_html(m):
        attr = m.group(1)
        path = m.group(2)
        
        if path.startswith('http') or path.startswith('#') or path.startswith('mailto:') or path.startswith('javascript:'):
            return m.group(0)
            
        basename = os.path.basename(path)
        if basename in file_destinations:
            dest_folder = file_destinations[basename]
            return f'{attr}="{prefix}{dest_folder}/{basename}"'
        elif basename in files:
            return f'{attr}="{prefix}{basename}"'
        elif path.startswith('includes/') or path.startswith('assets/'):
            return f'{attr}="{prefix}{path}"'
        else:
            return m.group(0)

    content = re.sub(r'(href|src|action)=["\']([^"\']+)["\']', repl_html, content)
    
    def repl_header(m):
        path = m.group(1)
        if path.startswith('http'):
            return m.group(0)
        
        basename = os.path.basename(path)
        if basename in file_destinations:
            dest_folder = file_destinations[basename]
            return f'header("Location: {prefix}{dest_folder}/{basename}")'
        elif basename in files:
            return f'header("Location: {prefix}{basename}")'
        else:
            return m.group(0)
            
    content = re.sub(r'header\(\s*["\']Location:\s*([^"\']+)["\']\s*\)', repl_header, content)

    return content

for f in os.listdir(base_dir):
    path = os.path.join(base_dir, f)
    if os.path.isfile(path) and f.endswith(('.php', '.html', '.js', '.css')):
        with open(path, 'r', encoding='utf-8', errors='ignore') as file:
            content = file.read()
        new_content = fix_content(content, '')
        if new_content != content:
            with open(path, 'w', encoding='utf-8') as file:
                file.write(new_content)

subfolders = list(file_mapping.keys()) + ['includes']
for folder in subfolders:
    d = os.path.join(base_dir, folder)
    if os.path.exists(d):
        for f in os.listdir(d):
            path = os.path.join(d, f)
            if os.path.isfile(path) and f.endswith(('.php', '.html', '.js', '.css')):
                with open(path, 'r', encoding='utf-8', errors='ignore') as file:
                    content = file.read()
                new_content = fix_content(content, folder)
                if new_content != content:
                    with open(path, 'w', encoding='utf-8') as file:
                        file.write(new_content)

print("Refactoring complete.")
