import sys
from pathlib import Path
path = Path(sys.argv[1])
start = int(sys.argv[2])
end = int(sys.argv[3])
for index, line in enumerate(path.read_text().splitlines(), 1):
    if start <= index <= end:
        print(f'{index}: {line}')
