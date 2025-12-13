#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
編譯 .po 文件為 .mo 文件的腳本
使用更可靠的方法來解析和編譯 gettext .po 文件
"""

import os
import sys
import struct
import re
from pathlib import Path

def unescape_string(s):
    """處理 .po 文件中的轉義序列"""
    s = s.replace('\\n', '\n')
    s = s.replace('\\t', '\t')
    s = s.replace('\\r', '\r')
    s = s.replace('\\"', '"')
    s = s.replace('\\\\', '\\')
    return s

def parse_po_file(po_path):
    """解析 .po 文件並返回翻譯字典"""
    translations = {}
    current_msgid = None
    current_msgstr = None
    
    with open(po_path, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # 使用正則表達式匹配 msgid 和 msgstr
    # 匹配格式：msgid "..." 或 msgid "" (多行)
    pattern = r'msgid\s+"(.*?)"(?:\s+msgstr\s+"(.*?)")?'
    
    # 更簡單的方法：逐行解析
    lines = content.split('\n')
    i = 0
    
    while i < len(lines):
        line = lines[i].strip()
        
        # 跳過空行和註釋
        if not line or line.startswith('#'):
            i += 1
            continue
        
        # 解析 msgid
        if line.startswith('msgid '):
            msgid_parts = []
            # 提取第一行的 msgid
            match = re.match(r'msgid\s+"(.*)"', line)
            if match:
                msgid_parts.append(match.group(1))
            elif 'msgid ""' in line:
                msgid_parts.append('')
            
            # 讀取多行 msgid
            i += 1
            while i < len(lines):
                next_line = lines[i].strip()
                if next_line.startswith('"') and next_line.endswith('"'):
                    msgid_parts.append(next_line[1:-1])
                    i += 1
                else:
                    break
            
            current_msgid = unescape_string(''.join(msgid_parts))
            current_msgstr = None
            continue
        
        # 解析 msgstr
        if line.startswith('msgstr '):
            msgstr_parts = []
            # 提取第一行的 msgstr
            match = re.match(r'msgstr\s+"(.*)"', line)
            if match:
                msgstr_parts.append(match.group(1))
            elif 'msgstr ""' in line:
                msgstr_parts.append('')
            
            # 讀取多行 msgstr
            i += 1
            while i < len(lines):
                next_line = lines[i].strip()
                if next_line.startswith('"') and next_line.endswith('"'):
                    msgstr_parts.append(next_line[1:-1])
                    i += 1
                else:
                    break
            
            current_msgstr = unescape_string(''.join(msgstr_parts))
            
            # 保存翻譯
            if current_msgid is not None:
                translations[current_msgid] = current_msgstr if current_msgstr else current_msgid
                current_msgid = None
                current_msgstr = None
            continue
        
        i += 1
    
    return translations

def write_mo_file(translations, mo_path):
    """將翻譯字典寫入 .mo 文件（二進制格式）"""
    # 構建字符串表
    original_strings = []
    translation_strings = []
    
    # 添加空字符串（索引 0，用於文件頭信息）
    original_strings.append(b'')
    translation_strings.append(b'')
    
    # 添加所有翻譯（按 msgid 排序以保持一致性）
    sorted_items = sorted(translations.items())
    for msgid, msgstr in sorted_items:
        if msgid:  # 跳過空字符串（已在索引 0）
            msgid_bytes = msgid.encode('utf-8')
            msgstr_bytes = (msgstr if msgstr else msgid).encode('utf-8')
            
            original_strings.append(msgid_bytes)
            translation_strings.append(msgstr_bytes)
    
    # 計算偏移量
    magic = 0x950412de
    version = 0
    num_strings = len(original_strings)
    
    # 計算表偏移量
    header_size = 28  # 7 * 4 bytes
    original_table_offset = header_size
    translation_table_offset = original_table_offset + (num_strings * 8)  # 每個條目 8 bytes (length + offset)
    hash_table_size = 0
    hash_table_offset = translation_table_offset + (num_strings * 8)
    
    # 計算字符串數據偏移量
    string_data_offset = hash_table_offset
    
    # 構建原始字符串表
    original_table = []
    current_offset = string_data_offset
    
    # 計算原始字符串的總長度
    original_strings_data = b''
    for msgid_bytes in original_strings:
        original_table.append((len(msgid_bytes), current_offset))
        original_strings_data += msgid_bytes + b'\x00'
        current_offset += len(msgid_bytes) + 1
    
    # 計算翻譯字符串表
    translation_table = []
    translation_strings_data = b''
    for msgstr_bytes in translation_strings:
        translation_table.append((len(msgstr_bytes), current_offset))
        translation_strings_data += msgstr_bytes + b'\x00'
        current_offset += len(msgstr_bytes) + 1
    
    # 寫入文件
    with open(mo_path, 'wb') as f:
        # 寫入魔數和版本
        f.write(struct.pack('<I', magic))
        f.write(struct.pack('<I', version))
        f.write(struct.pack('<I', num_strings))
        f.write(struct.pack('<I', original_table_offset))
        f.write(struct.pack('<I', translation_table_offset))
        f.write(struct.pack('<I', hash_table_size))
        f.write(struct.pack('<I', hash_table_offset))
        
        # 寫入原始字符串表
        f.seek(original_table_offset)
        for length, offset in original_table:
            f.write(struct.pack('<I', length))
            f.write(struct.pack('<I', offset))
        
        # 寫入翻譯字符串表
        f.seek(translation_table_offset)
        for length, offset in translation_table:
            f.write(struct.pack('<I', length))
            f.write(struct.pack('<I', offset))
        
        # 寫入字符串數據
        f.seek(string_data_offset)
        f.write(original_strings_data)
        f.write(translation_strings_data)

def main():
    """主函數"""
    script_dir = Path(__file__).parent
    po_files = list(script_dir.glob('*.po'))
    
    if not po_files:
        print("No .po files found")
        return
    
    print(f"Found {len(po_files)} .po files")
    
    for po_file in po_files:
        print(f"Compiling: {po_file.name}...")
        
        try:
            translations = parse_po_file(po_file)
            if not translations:
                print(f"  Warning: No translations found in {po_file.name}")
                continue
            
            mo_file = po_file.with_suffix('.mo')
            write_mo_file(translations, mo_file)
            print(f"  Success: {mo_file.name} ({len(translations)} translations)")
        except Exception as e:
            print(f"  Error: {e}")
            import traceback
            traceback.print_exc()

if __name__ == '__main__':
    main()
